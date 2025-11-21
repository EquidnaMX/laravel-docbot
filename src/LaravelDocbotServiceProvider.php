<?php

/**
 * Registers the Laravel Docbot package within a host application.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot
 * @author    Gabriel Ruelas <gruelasjr@gmail.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\LaravelDocbot;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Equidna\LaravelDocbot\Commands\Filters\ConfigExclusionFilter;
use Equidna\LaravelDocbot\Commands\Writers\MarkdownCommandWriter;
use Equidna\LaravelDocbot\Console\Commands\GenerateCommands;
use Equidna\LaravelDocbot\Console\Commands\GenerateRoutes;
use Equidna\LaravelDocbot\Contracts\CommandFilter;
use Equidna\LaravelDocbot\Contracts\CommandWriter;
use Equidna\LaravelDocbot\Contracts\RouteCollector;
use Equidna\LaravelDocbot\Contracts\RouteSegmentResolver;
use Equidna\LaravelDocbot\Contracts\RouteWriter;
use Equidna\LaravelDocbot\Routing\Collectors\RouterRouteCollector;
use Equidna\LaravelDocbot\Routing\RouteWriterManager;
use Equidna\LaravelDocbot\Routing\Segments\ConfigSegmentResolver;
use Equidna\LaravelDocbot\Routing\Support\RouteDescriptionExtractor;
use Equidna\LaravelDocbot\Routing\Writers\MarkdownRouteWriter;
use Equidna\LaravelDocbot\Routing\Writers\PostmanRouteWriter;
use Equidna\LaravelDocbot\Support\WriterFilesystem;
use Equidna\LaravelDocbot\Support\PathGuard;
use Equidna\LaravelDocbot\Support\ValueHelper;
use RuntimeException;

/**
 * Boots and registers the Laravel Docbot package services.
 */
class LaravelDocbotServiceProvider extends ServiceProvider
{
    /**
     * Registers package bindings and configuration.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/docbot.php',
            'docbot',
        );

        $this->registerRouteServices();
        $this->registerCommandServices();
    }

    /**
     * Boots the package services and publishes configuration.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    GenerateCommands::class,
                    GenerateRoutes::class,
                ],
            );

            $this->publishes(
                [
                    __DIR__ . '/../config/docbot.php' => config_path('docbot.php'),
                ],
                'laravel-docbot:config',
            );
        }
    }

    /**
     * Registers container bindings for route documentation services.
     *
     * @return void
     */
    private function registerRouteServices(): void
    {
        $this->app->singleton(
            RouteDescriptionExtractor::class,
            RouteDescriptionExtractor::class
        );

        $this->app->singleton(
            WriterFilesystem::class,
            function (Container $app): WriterFilesystem {
                return new WriterFilesystem($app->make(Filesystem::class));
            }
        );

        $this->app->singleton(
            ConfigSegmentResolver::class,
            function (Container $app): ConfigSegmentResolver {
                /** @var ConfigRepository $config */
                $config = $app->make(ConfigRepository::class);

                return new ConfigSegmentResolver(
                    $this->resolveAssocConfig($config->get('docbot.route_defaults', [])),
                    $this->resolveSegmentDefinitions($config->get('docbot.segments', [])),
                );
            }
        );

        $this->app->singleton(
            RouteCollector::class,
            function (Container $app): RouteCollector {
                /** @var ConfigRepository $config */
                $config = $app->make(ConfigRepository::class);
                $collectorClass = $this->resolveTypedClassString(
                    $config->get('docbot.routes.collector', RouterRouteCollector::class),
                    RouterRouteCollector::class,
                    RouteCollector::class,
                );

                $collector = $app->make($collectorClass);

                if (!$collector instanceof RouteCollector) {
                    throw new RuntimeException('Docbot route collector must implement RouteCollector.');
                }

                return $collector;
            }
        );

        $this->app->singleton(
            RouteSegmentResolver::class,
            function (Container $app): RouteSegmentResolver {
                /** @var ConfigRepository $config */
                $config = $app->make(ConfigRepository::class);
                $resolverClass = $this->resolveTypedClassString(
                    $config->get('docbot.routes.segment_resolver', ConfigSegmentResolver::class),
                    ConfigSegmentResolver::class,
                    RouteSegmentResolver::class,
                );

                $resolver = $app->make($resolverClass);

                if (!$resolver instanceof RouteSegmentResolver) {
                    throw new RuntimeException('Docbot route segment resolver must implement RouteSegmentResolver.');
                }

                return $resolver;
            }
        );

        $this->app->bind(
            MarkdownRouteWriter::class,
            function (Container $app): MarkdownRouteWriter {
                return new MarkdownRouteWriter(
                    $app->make(WriterFilesystem::class),
                    $app->make(RouteDescriptionExtractor::class),
                );
            }
        );

        $this->app->bind(PostmanRouteWriter::class, function (Container $app): PostmanRouteWriter {
            return new PostmanRouteWriter($app->make(WriterFilesystem::class));
        });

        $this->app->singleton(
            RouteWriterManager::class,
            function (Container $app): RouteWriterManager {
                /** @var ConfigRepository $config */
                $config = $app->make(ConfigRepository::class);
                $writers = $this->resolveTypedClassList(
                    $config->get('docbot.routes.writers'),
                    [
                        MarkdownRouteWriter::class,
                        PostmanRouteWriter::class,
                    ],
                    RouteWriter::class,
                );

                return new RouteWriterManager(
                    $app,
                    $writers,
                );
            }
        );
    }

    /**
     * Registers bindings used by command documentation pipeline.
     *
     * @return void
     */
    private function registerCommandServices(): void
    {
        $this->app->bind(ConfigExclusionFilter::class, function (Container $app): ConfigExclusionFilter {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);

            return new ConfigExclusionFilter(
                $this->resolveStringList($config->get('docbot.exclude_namespaces', [])),
                $this->resolveStringList($config->get('docbot.exclude_commands', [])),
            );
        });

        $this->app->bind(MarkdownCommandWriter::class, function (Container $app): MarkdownCommandWriter {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            $outputRoot = $this->resolveOutputRoot($config);
            $outputDir = PathGuard::join($outputRoot, 'commands');
            $filename = $this->resolveString(
                $config->get('docbot.commands.output_filename', 'project_commands.md'),
                'project_commands.md',
            );

            return new MarkdownCommandWriter(
                $app->make(WriterFilesystem::class),
                $outputDir,
                $filename,
            );
        });

        $this->app->bind(CommandWriter::class, function (Container $app): CommandWriter {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            $class = $this->resolveTypedClassString(
                $config->get('docbot.commands.writer', MarkdownCommandWriter::class),
                MarkdownCommandWriter::class,
                CommandWriter::class,
            );

            $writer = $app->make($class);

            if (!$writer instanceof CommandWriter) {
                throw new RuntimeException('Docbot command writer must implement CommandWriter.');
            }

            return $writer;
        });

        /**
         * @return array<int, CommandFilter>
         */
        $this->app->singleton('docbot.command_filters', function (Container $app): array {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            $classes = $this->resolveTypedClassList(
                $config->get('docbot.commands.filters'),
                [ConfigExclusionFilter::class],
                CommandFilter::class,
            );

            /** @var array<int, CommandFilter> $filters */
            $filters = [];

            foreach ($classes as $class) {
                $filter = $app->make($class);

                if (!$filter instanceof CommandFilter) {
                    throw new RuntimeException('Docbot command filters must implement CommandFilter.');
                }

                $filters[] = $filter;
            }

            return $filters;
        });

        $this->app->when(GenerateCommands::class)
            ->needs('$filters')
            ->give(static function (Container $app): array {
                $filters = $app->make('docbot.command_filters');

                if (!is_array($filters)) {
                    throw new RuntimeException('Docbot command filters must resolve to an array.');
                }

                return $filters;
            });
    }
    /**
     * @template T of object
     * @param  mixed             $value
     * @param  class-string<T>   $fallback
     * @param  class-string<T>   $contract
     * @return class-string<T>
     */
    private function resolveTypedClassString(mixed $value, string $fallback, string $contract): string
    {
        if (is_string($value) && class_exists($value) && is_a($value, $contract, true)) {
            return $value;
        }

        return $fallback;
    }

    /**
     * @template T of object
     * @param  mixed                           $value
     * @param  array<int, class-string<T>>     $fallback
     * @param  class-string<T>                 $contract
     * @return array<int, class-string<T>>
     */
    private function resolveTypedClassList(mixed $value, array $fallback, string $contract): array
    {
        if (!is_array($value)) {
            return $fallback;
        }

        $classes = [];

        foreach ($value as $class) {
            if (is_string($class) && class_exists($class) && is_a($class, $contract, true)) {
                $classes[] = $class;
            }
        }

        return $classes !== [] ? $classes : $fallback;
    }

    /**
     * @param  mixed $value
     * @return array<int, string>
     */
    private function resolveStringList(mixed $value): array
    {
        return ValueHelper::stringList($value);
    }

    /**
     * @param  mixed  $value
     * @param  string $fallback
     * @return string
     */
    private function resolveString(mixed $value, string $fallback = ''): string
    {
        return ValueHelper::stringOrFallback($value, $fallback);
    }

    /**
     * Resolves the guarded Docbot output root ensuring it stays within base_path().
     *
     * @param  ConfigRepository $config
     * @return string
     */
    private function resolveOutputRoot(ConfigRepository $config): string
    {
        return PathGuard::resolveOutputRoot($config->get('docbot.output_dir'));
    }

    /**
     * @param  mixed $value
     * @return array<string, mixed>
     */
    private function resolveAssocConfig(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return $this->filterStringKeys($value);
    }

    /**
     * @param  mixed $value
     * @return array<int, array<string, mixed>>
     */
    private function resolveSegmentDefinitions(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $segments = [];

        foreach ($value as $definition) {
            if (is_array($definition)) {
                $segments[] = $this->filterStringKeys($definition);
            }
        }

        return $segments;
    }

    /**
     * @param  array<mixed> $items
     * @return array<string, mixed>
     */
    private function filterStringKeys(array $items): array
    {
        $assoc = [];

        foreach ($items as $key => $value) {
            if (is_string($key)) {
                $assoc[$key] = $value;
            }
        }

        return $assoc;
    }
}
