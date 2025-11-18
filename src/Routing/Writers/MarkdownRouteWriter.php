<?php

/**
 * Route writer that produces Markdown tables grouped by route name segments.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Routing\Writers
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\LaravelDocbot\Routing\Writers;

use Equidna\LaravelDocbot\Contracts\RouteWriter;
use Equidna\LaravelDocbot\Routing\Support\RouteDescriptionExtractor;
use Equidna\LaravelDocbot\Routing\Support\Sanitizer;
use Equidna\LaravelDocbot\Support\ValueHelper;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Persists Markdown route documentation for each segment.
 *
 * @phpstan-type DocbotRoute array{
 *   methods: array<int, string>,
 *   uri: string,
 *   name: string|null,
 *   action: string|null,
 *   middleware: array<int, string>,
 *   domain: string|null,
 *   path_parameters: array<int, string>
 * }
 * @phpstan-type DocbotSegment array{
 *   key?: string,
 *   host_variable?: string,
 *   host_value?: string,
 *   auth?: array{
 *     type?: string,
 *     header?: string,
 *     token_variable?: string
 *   }
 * }
 */
final class MarkdownRouteWriter implements RouteWriter
{
    /**
     * @param  Filesystem                $filesystem   Filesystem instance for writes.
     * @param  RouteDescriptionExtractor $descriptions Description extractor helper.
     */
    public function __construct(
        private Filesystem $filesystem,
        private RouteDescriptionExtractor $descriptions,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function format(): string
    {
        return 'markdown';
    }

    /**
     * {@inheritDoc}
     *
     * @param  DocbotSegment             $segment
     * @param  array<int, DocbotRoute>   $routes
     * @return void
     */
    public function write(
        array $segment,
        array $routes,
        string $path,
    ): void {
        $document = $this->buildDocument($segment, $routes);

        $segmentKey = Sanitizer::filename($segment['safe_key'] ?? $segment['key'] ?? null);

        $filePath = rtrim($path, '/\\') . '/' . $segmentKey . '.md';

        try {
            $this->filesystem->ensureDirectoryExists(dirname($filePath));
            $this->filesystem->put($filePath, $document);
        } catch (\Throwable $e) {
            $msg = sprintf('Failed to write Markdown route documentation to "%s": %s', $filePath, $e->getMessage());

            throw new \RuntimeException($msg, 0, $e);
        }
    }

    /**
     * Builds the Markdown file for the segment.
     *
     * @param  DocbotSegment             $segment
     * @param  array<int, DocbotRoute>   $routes
     * @return string
     */
    private function buildDocument(
        array $segment,
        array $routes,
    ): string {
        $segKey = ValueHelper::stringOrFallback($segment['key'] ?? null, 'unknown');
        $hostVar = ValueHelper::stringOrFallback($segment['host_variable'] ?? null, 'host');
        $hostVal = ValueHelper::stringOrFallback($segment['host_value'] ?? null, '');

        $header = sprintf("# %s documentation\n\n", $segKey);
        $header .= sprintf("**Base URL:** `{{%s}}` (defaults to %s).\n\n", $hostVar, $hostVal);

        if (!empty($segment['auth']) && is_array($segment['auth'])) {
            $auth = $segment['auth'];
            $type = Str::ucfirst(ValueHelper::stringOrFallback($auth['type'] ?? null, 'unknown'));
            $tokenVar = ValueHelper::stringOrFallback($auth['token_variable'] ?? null, 'token');
            $headerName = ValueHelper::stringOrFallback($auth['header'] ?? null, 'Authorization');

            $header .= sprintf(
                "Authenticated via %s `{{%s}}` in the `%s` header.\n\n",
                $type,
                $tokenVar,
                $headerName,
            );
        } else {
            $header .= "Authentication: not required.\n\n";
        }

        $groups = [];

        foreach ($routes as $route) {
            $name = ValueHelper::stringOrFallback($route['name'] ?? null, 'misc');
            $parts = explode('.', $name === '' ? 'misc' : $name);
            $groupKey = $parts[0] ?? 'misc';
            $groups[$groupKey][] = $route;
        }

        $document = $header;

        foreach ($groups as $group => $items) {
            $document .= sprintf("## %s\n", (string) $group);
            $document .= "| Method | Path | Description | Path Params |\n";
            $document .= "| ------ | ---- | ----------- | ----------- |\n";

            foreach ($items as $route) {
                $methods = $this->normalizeList($route['methods'] ?? []);
                $params = $this->normalizeList($route['path_parameters'] ?? []);
                $uri = Sanitizer::cell(ValueHelper::stringOrFallback($route['uri'] ?? null, ''));
                $action = ValueHelper::stringOrFallback($route['action'] ?? null, '');
                $desc = Sanitizer::cell($this->descriptions->extract($action));
                $params = Sanitizer::cell($params);

                $document .= sprintf(
                    "| %s | `%s` | %s | %s |\n",
                    $methods,
                    $uri,
                    $desc,
                    $params,
                );
            }

            $document .= "\n";
        }

        return $document;
    }

    // Cell sanitization moved to Routing\Support\Sanitizer::cell

    /**
     * Normalizes a list of scalar-ish values into a comma-separated string.
     *
     * @param  iterable<int, mixed>|string $values
     * @return string
     */
    private function normalizeList(iterable|string $values): string
    {
        if (is_string($values)) {
            return $values;
        }

        $normalized = [];

        foreach ($values as $value) {
            $stringValue = ValueHelper::stringOrNull($value);

            if ($stringValue === null) {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return implode(', ', $normalized);
    }
}
