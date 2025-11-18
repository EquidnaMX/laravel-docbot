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

declare(strict_types=1);

namespace Equidna\LaravelDocbot\Routing\Writers;

use Equidna\LaravelDocbot\Contracts\RouteWriter;
use Equidna\LaravelDocbot\Routing\Support\RouteDescriptionExtractor;
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

        $segmentKey = $this->stringOrFallback($segment['key'] ?? null, 'unknown');

        $this->filesystem->put(
            rtrim($path, '/\\') . '/' . $segmentKey . '.md',
            $document,
        );
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
        $segKey = $this->stringOrFallback($segment['key'] ?? null, 'unknown');
        $hostVar = $this->stringOrFallback($segment['host_variable'] ?? null, 'host');
        $hostVal = $this->stringOrFallback($segment['host_value'] ?? null, '');

        $header = sprintf("# %s documentation\n\n", $segKey);
        $header .= sprintf("**Base URL:** `{{%s}}` (defaults to %s).\n\n", $hostVar, $hostVal);

        if (!empty($segment['auth']) && is_array($segment['auth'])) {
            $auth = $segment['auth'];
            $type = Str::ucfirst($this->stringOrFallback($auth['type'] ?? null, 'unknown'));
            $tokenVar = $this->stringOrFallback($auth['token_variable'] ?? null, 'token');
            $headerName = $this->stringOrFallback($auth['header'] ?? null, 'Authorization');

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
            $name = $this->stringOrFallback($route['name'] ?? null, 'misc');
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
                $uri = $this->sanitizeCell($this->stringOrFallback($route['uri'] ?? null, ''));
                $action = $this->stringOrFallback($route['action'] ?? null, '');
                $desc = $this->sanitizeCell($this->descriptions->extract($action));
                $params = $this->sanitizeCell($params);

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

    /**
     * Sanitize table cell content: remove newlines and escape pipes/backticks.
     *
     * @param  string $text
     * @return string
     */
    private function sanitizeCell(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = str_replace('|', '\\|', $text);
        $text = str_replace('`', '\\`', $text);

        return trim($text);
    }

    /**
     * Returns the string representation of a value or null.
     *
     * @param  mixed $value
     * @return string|null
     */
    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Returns a string or the provided fallback when missing.
     *
     * @param  mixed   $value
     * @param  string  $fallback
     * @return string
     */
    private function stringOrFallback(mixed $value, string $fallback): string
    {
        return $this->stringOrNull($value) ?? $fallback;
    }

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
            $stringValue = $this->stringOrNull($value);

            if ($stringValue === null) {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return implode(', ', $normalized);
    }
}
