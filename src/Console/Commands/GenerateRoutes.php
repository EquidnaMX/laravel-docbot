<?php

/**
 * Console command responsible for building Markdown and Postman docs for routes.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Console\Commands
 */

namespace Equidna\LaravelDocbot\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Equidna\LaravelDocbot\Contracts\RouteCollector;
use Equidna\LaravelDocbot\Contracts\RouteSegmentResolver;
use Equidna\LaravelDocbot\Routing\RouteWriterManager;
use Illuminate\Contracts\Container\Container;

/**
 * Generates Markdown and Postman documentation for the configured route segments.
 */
class GenerateRoutes extends Command
{
    /**
     * Creates a new instance of the command with its dependencies.
     *
     * @param RouteCollector       $collector        Service that normalizes routes.
     * @param RouteSegmentResolver $segmentResolver  Resolver for configured segments.
     * @param RouteWriterManager   $writerManager    Registered documentation writers.
     */
    public function __construct(
        private RouteCollector $collector,
        private RouteSegmentResolver $segmentResolver,
        private RouteWriterManager $writerManager,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected $signature = 'docbot:routes {--segment=* : Only process the provided segment keys} {--format=* : Limit output formats (markdown, postman)}';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Generates API documentation and Postman collections.';

    /**
     * Executes the command and generates the requested artifacts.
     *
     * @return int
     */
    public function handle(): int
    {
        $writers = $this->writerManager->all();

        if (empty($writers)) {
            $this->error('No Docbot route writers are registered.');

            return self::FAILURE;
        }

        $formats = $this->resolveFormats(array_keys($writers));

        if (empty($formats)) {
            return self::FAILURE;
        }

        $segments = $this->segmentResolver->segments();

        if (empty($segments)) {
            $this->error('No Docbot segments are configured.');

            return self::FAILURE;
        }

        $selectedSegments = $this->filterSegments($segments);

        if (empty($selectedSegments)) {
            $this->error('No matching segments found for the provided filter.');

            return self::FAILURE;
        }

        $routes = $this->collector->collect();

        if (empty($routes)) {
            $this->warn('No routes registered in the application.');

            return self::SUCCESS;
        }

        $this->components->info('Segments: ' . implode(', ', array_keys($selectedSegments)));
        $this->components->info('Formats : ' . implode(', ', $formats));

        $routesBySegment = $this->splitRoutes(
            $routes,
            $selectedSegments,
        );

        foreach ($selectedSegments as $key => $segment) {
            $routesForSegment = $routesBySegment[$key] ?? [];
            $routeCount = count($routesForSegment);

            $this->components->info(
                sprintf(
                    "Segment '%s' route count: %d",
                    $key,
                    $routeCount,
                ),
            );

            if ($routeCount === 0) {
                continue;
            }

            $directory = $this->segmentOutputPath($segment);
            File::ensureDirectoryExists($directory);

            foreach ($formats as $format) {
                $writers[$format]->write(
                    $segment,
                    $routesForSegment,
                    $directory,
                );
            }
        }

        $this->components->info('Route documentation generated successfully.');

        return self::SUCCESS;
    }

    /**
     * Filters the configured segments with the --segment option.
     *
     * @param  array<string, array<string, mixed>> $segments
     * @return array<string, array<string, mixed>>
     */
    private function filterSegments(array $segments): array
    {
        $filter = array_filter((array) ($this->option('segment') ?? []));

        if (empty($filter)) {
            return $segments;
        }

        $filtered = [];

        foreach ($filter as $rawKey) {
            $key = $this->stringOrNull($rawKey);

            if ($key === null) {
                $this->warn('Skipping non-string segment filter value.');

                continue;
            }

            if (!array_key_exists($key, $segments)) {
                $this->warn("Segment '" . $key . "' is not defined in config/docbot.php");

                continue;
            }

            $filtered[$key] = $segments[$key];
        }

        return $filtered;
    }

    /**
     * Splits the described routes into their configured segments.
     *
     * @param  array<int, array<string, mixed>>        $routes
     * @param  array<string, array<string, mixed>>    $segments
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function splitRoutes(
        array $routes,
        array $segments,
    ): array {
        $result = [];

        foreach ($segments as $key => $segment) {
            $result[$key] = [];
        }

        foreach ($routes as $route) {
            $assigned = false;

            foreach ($segments as $key => $segment) {
                if (isset($segment['key']) && $segment['key'] === 'web') {
                    continue;
                }

                if ($this->matchesSegment($route, $segment)) {
                    $result[$key][] = $route;
                    $assigned = true;

                    break;
                }
            }

            if (!$assigned && array_key_exists('web', $segments)) {
                $result['web'][] = $route;
            }
        }

        return $result;
    }

    /**
     * Evaluates whether a described route fits a segment definition.
     *
     * @param  array<string, mixed> $route
     * @param  array<string, mixed> $segment
     * @return bool
     */
    private function matchesSegment(
        array $route,
        array $segment,
    ): bool {
        // Guard types: ensure we have strings/arrays before using helpers
        if (!empty($segment['prefix'])) {
            if (!isset($route['uri']) || !is_string($route['uri']) || !is_string($segment['prefix'])) {
                return false;
            }

            if (!Str::startsWith($route['uri'], $segment['prefix'])) {
                return false;
            }
        }

        if (!empty($segment['domain'])) {
            if (!isset($route['domain']) || !is_string($route['domain']) || !is_string($segment['domain'])) {
                return false;
            }

            if ($segment['domain'] !== $route['domain']) {
                return false;
            }
        }

        // Middleware include/exclude checks
        $routeMiddleware = $route['middleware'] ?? [];
        if (!is_array($routeMiddleware) && !($routeMiddleware instanceof \Traversable)) {
            $routeMiddleware = [];
        }

        if (!empty($segment['include_middleware']) && is_iterable($segment['include_middleware'])) {
            foreach ($segment['include_middleware'] as $middleware) {
                if (!in_array($middleware, (array) $routeMiddleware, true)) {
                    return false;
                }
            }
        }

        if (!empty($segment['exclude_middleware']) && is_iterable($segment['exclude_middleware'])) {
            foreach ($segment['exclude_middleware'] as $middleware) {
                if (in_array($middleware, (array) $routeMiddleware, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Resolves which output formats should be generated.
     *
     * @param  array<int, string> $availableFormats
     * @return array<int, string>
     */
    private function resolveFormats(array $availableFormats): array
    {
        $requested = array_filter((array) ($this->option('format') ?? []));

        if (empty($requested)) {
            return $availableFormats;
        }

        $valid = [];

        foreach ($requested as $rawFormat) {
            $format = $this->stringOrNull($rawFormat);

            if ($format === null || !in_array($format, $availableFormats, true)) {
                $this->warn("Unsupported format '" . ($format ?? 'unknown') . "' ignored.");

                continue;
            }

            $valid[] = $format;
        }

        return array_values(array_unique($valid));
    }

    /**
     * Computes the output path for a specific segment.
     *
     * @param  array<string, mixed> $segment
     * @return string
     */
    private function segmentOutputPath(array $segment): string
    {
        $rootConfig = config('docbot.output_dir');
        $rootValue = $this->stringOrNull($rootConfig);
        $root = rtrim($rootValue ?? base_path('doc'), '/\\');

        $key = $this->stringOrNull($segment['key'] ?? null, 'unknown');

        return $root . '/routes/' . $key;
    }

    /**
     * @param  mixed       $value
     * @param  string|null $fallback
     * @return string|null
     */
    private function stringOrNull(mixed $value, ?string $fallback = null): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $fallback;
    }
}
