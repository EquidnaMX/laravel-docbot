<?php

/**
 * Route writer that emits Postman collection JSON.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Routing\Writers
 */

namespace Equidna\LaravelDocbot\Routing\Writers;

use Illuminate\Filesystem\Filesystem;
use Equidna\LaravelDocbot\Contracts\RouteWriter;

/**
 * Persists Postman v2.1 collections for each Docbot segment.
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
 * @phpstan-type FlattenedItem array{
 *   segments: array<int, string>,
 *   route: DocbotRoute,
 *   method: string
 * }
 * @phpstan-type DocbotVariable array{
 *   key: string,
 *   value: string,
 *   type: string
 * }
 */
final class PostmanRouteWriter implements RouteWriter
{
    /**
     * @param  Filesystem $filesystem Filesystem writer instance.
     */
    public function __construct(
        private Filesystem $filesystem,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function format(): string
    {
        return 'postman';
    }

    /**
     * {@inheritDoc}
     *
     * @param  DocbotSegment           $segment
     * @param  array<int, DocbotRoute> $routes
     * @return void
     */
    public function write(
        array $segment,
        array $routes,
        string $path,
    ): void {
        $collection = $this->buildCollection($segment, $routes);

        $segmentKey = $this->stringOrFallback($segment['key'] ?? null, 'unknown');

        $this->filesystem->put(
            rtrim($path, '/\\') . '/' . $segmentKey . '.json',
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        );
    }

    /**
     * Builds the Postman collection payload.
     *
     * @param  DocbotSegment           $segment
     * @param  array<int, DocbotRoute> $routes
     * @return array<string, mixed>
     */
    private function buildCollection(
        array $segment,
        array $routes,
    ): array {
        $hostVar = $this->stringOrFallback($segment['host_variable'] ?? null, 'host');
        $hostVal = $this->stringOrFallback($segment['host_value'] ?? null, '');

        $variables = [
            [
                'key' => $hostVar,
                'value' => $hostVal,
                'type' => 'text',
            ],
        ];

        if (!empty($segment['auth']) && is_array($segment['auth'])) {
            $tokenVar = $this->stringOrNull($segment['auth']['token_variable'] ?? null);

            if ($tokenVar !== null) {
                $variables[] = [
                    'key' => $tokenVar,
                    'value' => '',
                    'type' => 'secret',
                ];
            }
        }

        $this->appendPathParams(
            $routes,
            $variables,
        );

        $segKey = $this->stringOrFallback($segment['key'] ?? null, 'unknown');

        $collection = [
            'info' => [
                'name' => $segKey . ' API',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => $this->buildItems(
                $segment,
                $routes,
            ),
            'variable' => $variables,
        ];

        if (!empty($segment['auth']) && is_array($segment['auth'])) {
            $authBlock = $this->buildAuth($segment['auth']);

            if (!empty($authBlock)) {
                $collection['auth'] = $authBlock;
            }
        }

        return $collection;
    }

    /**
     * Returns the string representation of the value or null.
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
     * Returns the string value or the provided fallback when absent.
     *
     * @param  mixed  $value
     * @param  string $fallback
     * @return string
     */
    private function stringOrFallback(mixed $value, string $fallback): string
    {
        return $this->stringOrNull($value) ?? $fallback;
    }

    /**
     * Appends any discovered path params to the Postman variables array.
     *
     * @param  array<int, DocbotRoute>    $routes
     * @param  array<int, DocbotVariable> $variables
     * @return void
     */
    private function appendPathParams(
        array $routes,
        array &$variables,
    ): void {
        $existingKeys = array_column($variables, 'key');

        foreach ($routes as $route) {
            if (empty($route['path_parameters'])) {
                continue;
            }

            foreach ($route['path_parameters'] as $param) {
                $paramKey = $this->stringOrNull($param);

                if ($paramKey === null || in_array($paramKey, $existingKeys, true)) {
                    continue;
                }

                $variables[] = [
                    'key' => $paramKey,
                    'value' => '',
                    'type' => 'text',
                ];
                $existingKeys[] = $paramKey;
            }
        }
    }

    /**
     * Builds the tree of Postman items grouped by dotted route name segments.
     *
     * @param  DocbotSegment           $segment
     * @param  array<int, DocbotRoute> $routes
     * @return list<array<string, mixed>>
     */
    private function buildItems(
        array $segment,
        array $routes,
    ): array {
        /** @var array<int, FlattenedItem> $items */
        $items = [];

        foreach ($routes as $route) {
            /** @var DocbotRoute $route */
            if (!isset($route['methods']) || !is_iterable($route['methods'])) {
                continue;
            }

            foreach ($route['methods'] as $method) {
                $name = $this->stringOrNull($route['name'] ?? null);

                $normalizedMethod = $this->stringOrNull($method);

                if ($normalizedMethod === null) {
                    continue;
                }

                $items[] = $this->makeFlattenedItemRow(
                    $route,
                    $normalizedMethod,
                    $name,
                );
            }
        }

        return $this->nestItems(
            $segment,
            $items,
        );
    }

    /**
     * Builds a flattened representation of a route/method tuple.
     *
     * @param  DocbotRoute $route
     * @param  string      $method
     * @param  string|null $name
     * @return FlattenedItem
     */
    private function makeFlattenedItemRow(
        array $route,
        string $method,
        ?string $name,
    ): array {
        return [
            'segments' => $this->determineFolderSegments($name),
            'route' => $route,
            'method' => $method,
        ];
    }

    /**
     * Recursively reduces flattened items into nested folders.
     *
     * @param  DocbotSegment             $segment
     * @param  array<int, FlattenedItem> $items
     * @return list<array<string, mixed>>
     */
    private function nestItems(
        array $segment,
        array $items,
    ): array {
        /** @var list<array<string, mixed>> $requests */
        $requests = [];
        /** @var array<string, array<int, FlattenedItem>> $folders */
        $folders = [];

        foreach ($items as $item) {
            if (!isset($item['segments'], $item['route'], $item['method'])) {
                continue;
            }

            $segments = $item['segments'];

            if (count($segments) <= 1) {
                $requests[] = $this->makeRequestItem(
                    $segment,
                    $item['route'],
                    $item['method'],
                );

                continue;
            }

            $first = array_shift($segments);
            $folder = $this->stringOrFallback($first, 'misc');

            $folders[$folder][] = [
                'segments' => array_values($segments),
                'route' => $item['route'],
                'method' => $item['method'],
            ];
        }

        $result = $requests;

        foreach ($folders as $name => $children) {
            $result[] = [
                'name' => $name,
                'item' => $this->nestItems($segment, $children),
            ];
        }

        return $result;
    }

    /**
     * Builds an individual request definition for Postman.
     *
     * @param  DocbotSegment $segment
     * @param  DocbotRoute   $route
     * @param  string        $method
     * @return array<string, mixed>
     */
    private function makeRequestItem(
        array $segment,
        array $route,
        string $method,
    ): array {
        $uriWithVars = $this->convertUri($this->stringOrFallback($route['uri'] ?? null, ''));
        $headers = $this->buildHeaders($segment);
        $hostPlaceholder = $this->buildHostPlaceholder($segment);

        return [
            'name' => $this->determineRequestName(
                $route,
                $method,
            ),
            'event' => [
                [
                    'listen' => 'test',
                    'script' => [
                        'exec' => [
                            "pm.test('Status is 2xx', () => pm.response.code >= 200 && pm.response.code < 300);",
                        ],
                    ],
                ],
            ],
            'request' => [
                'method' => $method,
                'header' => $headers,
                'url' => [
                    'raw' => $hostPlaceholder . '/' . ltrim($uriWithVars, '/'),
                    'host' => [
                        $hostPlaceholder,
                    ],
                    'path' => $this->splitUri($uriWithVars),
                ],
            ],
        ];
    }

    /**
     * Builds headers required for header-based authentication segments.
     *
     * @param  DocbotSegment $segment
     * @return array<int, DocbotVariable>
     */
    private function buildHeaders(array $segment): array
    {
        if (empty($segment['auth']) || !is_array($segment['auth'])) {
            return [];
        }

        if (($segment['auth']['type'] ?? null) !== 'header') {
            return [];
        }

        $headerKey = $this->stringOrFallback($segment['auth']['header'] ?? null, 'Authorization');
        $tokenVar = $this->stringOrFallback($segment['auth']['token_variable'] ?? null, 'token');

        return [
            [
                'key' => $headerKey,
                'value' => '{{' . $tokenVar . '}}',
                'type' => 'text',
            ],
        ];
    }

    /**
     * Renders the Postman host placeholder for a segment.
     *
     * @param  DocbotSegment $segment
     * @return string
     */
    private function buildHostPlaceholder(array $segment): string
    {
        $variable = $this->stringOrFallback($segment['host_variable'] ?? null, 'host');

        return '{{' . $variable . '}}';
    }

    /**
     * Determines folder segments from a dotted route name.
     *
     * @param  string|null $name
     * @return array<int, string>
     */
    private function determineFolderSegments(?string $name): array
    {
        if (empty($name)) {
            return ['misc'];
        }

        $segments = explode('.', $name);

        // If there is only a single segment, place the request under 'misc'.
        if (count($segments) <= 1) {
            return ['misc'];
        }

        // Optionally strip known top-level prefixes that are conventional (e.g. 'api').
        $stripPrefixes = ['api'];

        if (in_array($segments[0], $stripPrefixes, true)) {
            array_shift($segments);
        }

        // Keep the first segment as the top-level folder for Postman.
        return $segments;
    }

    /**
     * Returns a readable request name for Postman entries.
     *
     * @param  DocbotRoute $route
     * @param  string      $method
     * @return string
     */
    private function determineRequestName(
        array $route,
        string $method,
    ): string {
        if (!empty($route['name'])) {
            return strtoupper($method) . ' ' . $this->stringOrFallback($route['name'], '');
        }

        return strtoupper($method) . ' ' . $this->stringOrFallback($route['uri'] ?? null, '');
    }

    /**
     * Converts Laravel URI placeholders to Postman variable syntax.
     *
     * @param  string $uri
     * @return string
     */
    private function convertUri(string $uri): string
    {
        $converted = preg_replace_callback(
            '/\{(\w+)\??\}/',
            static function (array $matches): string {
                return '{{' . $matches[1] . '}}';
            },
            $uri,
        );

        return is_string($converted) ? $converted : '';
    }

    /**
     * Splits URI into path segments for Postman.
     *
     * @param  string $uri
     * @return array<int, string>
     */
    private function splitUri(string $uri): array
    {
        $trimmed = trim($uri, '/');

        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }

    /**
     * Builds the Postman auth block based on config.
     *
     * @param  array{type?: string, header?: string, token_variable?: string} $auth
     * @return array<string, mixed>
     */
    private function buildAuth(array $auth): array
    {
        if (($auth['type'] ?? null) === 'bearer') {
            $tokenVar = $this->stringOrFallback($auth['token_variable'] ?? null, 'token');

            return [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{' . $tokenVar . '}}',
                        'type' => 'string',
                    ],
                ],
            ];
        }

        if (($auth['type'] ?? null) === 'header') {
            $headerVal = $this->stringOrFallback($auth['header'] ?? null, 'Authorization');
            $tokenVar = $this->stringOrFallback($auth['token_variable'] ?? null, 'token');

            return [
                'type' => 'apikey',
                'apikey' => [
                    [
                        'key' => 'key',
                        'value' => $headerVal,
                        'type' => 'string',
                    ],
                    [
                        'key' => 'value',
                        'value' => '{{' . $tokenVar . '}}',
                        'type' => 'string',
                    ],
                    [
                        'key' => 'in',
                        'value' => 'header',
                        'type' => 'string',
                    ],
                ],
            ];
        }

        return [];
    }
}
