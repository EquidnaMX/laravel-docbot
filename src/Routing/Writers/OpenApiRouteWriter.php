<?php

/**
 * Route writer that produces OpenAPI 3.0 specification.
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

/**
 * Persists OpenAPI 3.0 specification for each segment.
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
final class OpenApiRouteWriter implements RouteWriter
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
        return 'openapi';
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
        $specification = $this->buildSpecification($segment, $routes);

        $segmentKey = Sanitizer::filename($segment['safe_key'] ?? $segment['key'] ?? null);

        $filePath = rtrim($path, '/\\') . '/' . $segmentKey . '.yaml';

        $yaml = $this->convertToYaml($specification);

        try {
            $this->filesystem->ensureDirectoryExists(dirname($filePath));
            $this->filesystem->put($filePath, $yaml);
        } catch (\Throwable $e) {
            $msg = sprintf('Failed to write OpenAPI specification to "%s": %s', $filePath, $e->getMessage());

            throw new \RuntimeException($msg, 0, $e);
        }
    }

    /**
     * Builds the OpenAPI 3.0 specification for the segment.
     *
     * @param  DocbotSegment             $segment
     * @param  array<int, DocbotRoute>   $routes
     * @return array<string, mixed>
     */
    private function buildSpecification(
        array $segment,
        array $routes,
    ): array {
        $segKey = ValueHelper::stringOrFallback($segment['key'] ?? null, 'unknown');
        $hostVal = ValueHelper::stringOrFallback($segment['host_value'] ?? null, 'https://api.example.com');

        $parsedUrl = parse_url($hostVal);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? 'api.example.com';
        $basePath = $parsedUrl['path'] ?? '';

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $segKey . ' API',
                'description' => 'API documentation for ' . $segKey . ' segment',
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => rtrim($hostVal, '/'),
                    'description' => ucfirst($segKey) . ' server',
                ],
            ],
            'paths' => $this->buildPaths($routes),
        ];

        if (!empty($segment['auth']) && is_array($segment['auth'])) {
            $spec['components'] = [
                'securitySchemes' => $this->buildSecuritySchemes($segment['auth']),
            ];
            $spec['security'] = $this->buildSecurity($segment['auth']);
        }

        return $spec;
    }

    /**
     * Builds the paths object for OpenAPI specification.
     *
     * @param  array<int, DocbotRoute>   $routes
     * @return array<string, mixed>
     */
    private function buildPaths(array $routes): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $uri = '/' . ltrim(ValueHelper::stringOrFallback($route['uri'] ?? null, ''), '/');
            
            // Convert Laravel route parameters to OpenAPI format
            $uri = preg_replace('/\{(\w+)\?\}/', '{$1}', $uri);

            if (!isset($paths[$uri])) {
                $paths[$uri] = [];
            }

            $methods = $route['methods'] ?? [];
            if (!is_iterable($methods)) {
                $methods = [];
            }

            foreach ($methods as $method) {
                $methodKey = strtolower(ValueHelper::stringOrFallback($method, 'get'));
                $paths[$uri][$methodKey] = $this->buildOperation($route, $method);
            }
        }

        return $paths;
    }

    /**
     * Builds an operation object for a route/method combination.
     *
     * @param  DocbotRoute $route
     * @param  mixed       $method
     * @return array<string, mixed>
     */
    private function buildOperation(array $route, mixed $method): array
    {
        $action = ValueHelper::stringOrFallback($route['action'] ?? null, '');
        $description = $this->descriptions->extract($action);
        $name = ValueHelper::stringOrFallback($route['name'] ?? null, '');
        
        $methodStr = ValueHelper::stringOrFallback($method, 'GET');
        $summary = $name ?: (strtoupper($methodStr) . ' ' . ($route['uri'] ?? ''));

        $operation = [
            'summary' => $summary,
            'description' => $description ?: 'No description available',
            'operationId' => $this->generateOperationId($route, $methodStr),
            'responses' => $this->buildResponses(),
        ];

        $parameters = $this->buildParameters($route);
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Add request body for methods that typically have one
        if (in_array(strtoupper($methodStr), ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = $this->buildRequestBody();
        }

        // Add tags based on route name
        $tags = $this->extractTags($name);
        if (!empty($tags)) {
            $operation['tags'] = $tags;
        }

        return $operation;
    }

    /**
     * Generates a unique operation ID for the route.
     *
     * @param  DocbotRoute $route
     * @param  string      $method
     * @return string
     */
    private function generateOperationId(array $route, string $method): string
    {
        $name = ValueHelper::stringOrFallback($route['name'] ?? null, '');
        
        if ($name) {
            return strtolower($method) . '_' . str_replace('.', '_', $name);
        }

        $uri = ValueHelper::stringOrFallback($route['uri'] ?? null, 'unknown');
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $uri);
        
        return strtolower($method) . '_' . $sanitized;
    }

    /**
     * Builds parameters array for a route.
     *
     * @param  DocbotRoute $route
     * @return array<int, array<string, mixed>>
     */
    private function buildParameters(array $route): array
    {
        $parameters = [];

        $pathParams = $route['path_parameters'] ?? [];
        if (!is_iterable($pathParams)) {
            $pathParams = [];
        }

        foreach ($pathParams as $param) {
            $paramName = ValueHelper::stringOrNull($param);
            if ($paramName === null) {
                continue;
            }

            $parameters[] = [
                'name' => $paramName,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                ],
                'description' => 'Path parameter: ' . $paramName,
            ];
        }

        return $parameters;
    }

    /**
     * Builds default responses object.
     *
     * @return array<string, mixed>
     */
    private function buildResponses(): array
    {
        return [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
            '400' => [
                'description' => 'Bad request',
            ],
            '401' => [
                'description' => 'Unauthorized',
            ],
            '404' => [
                'description' => 'Not found',
            ],
            '500' => [
                'description' => 'Internal server error',
            ],
        ];
    }

    /**
     * Builds request body object for POST/PUT/PATCH requests.
     *
     * @return array<string, mixed>
     */
    private function buildRequestBody(): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds security schemes from auth configuration.
     *
     * @param  array{type?: string, header?: string, token_variable?: string} $auth
     * @return array<string, mixed>
     */
    private function buildSecuritySchemes(array $auth): array
    {
        $type = ValueHelper::stringOrFallback($auth['type'] ?? null, 'bearer');

        if ($type === 'bearer') {
            return [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ];
        }

        if ($type === 'header') {
            $headerName = ValueHelper::stringOrFallback($auth['header'] ?? null, 'Authorization');
            
            return [
                'apiKeyAuth' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => $headerName,
                ],
            ];
        }

        return [];
    }

    /**
     * Builds security requirement object.
     *
     * @param  array{type?: string, header?: string, token_variable?: string} $auth
     * @return array<int, array<string, array<int, string>>>
     */
    private function buildSecurity(array $auth): array
    {
        $type = ValueHelper::stringOrFallback($auth['type'] ?? null, 'bearer');

        if ($type === 'bearer') {
            return [
                ['bearerAuth' => []],
            ];
        }

        if ($type === 'header') {
            return [
                ['apiKeyAuth' => []],
            ];
        }

        return [];
    }

    /**
     * Extracts tags from route name.
     *
     * @param  string $name
     * @return array<int, string>
     */
    private function extractTags(string $name): array
    {
        if (empty($name)) {
            return ['general'];
        }

        $parts = explode('.', $name);
        
        // Remove common prefixes
        $stripPrefixes = ['api'];
        if (in_array($parts[0] ?? '', $stripPrefixes, true)) {
            array_shift($parts);
        }

        // Use the first part as the tag
        if (!empty($parts)) {
            return [ucfirst($parts[0])];
        }

        return ['general'];
    }

    /**
     * Converts an array to YAML format.
     *
     * @param  array<string, mixed> $data
     * @return string
     */
    private function convertToYaml(array $data): string
    {
        return $this->arrayToYaml($data, 0);
    }

    /**
     * Recursively converts an array to YAML string.
     *
     * @param  mixed $data
     * @param  int   $indent
     * @return string
     */
    private function arrayToYaml(mixed $data, int $indent): string
    {
        if (!is_array($data)) {
            return $this->yamlValue($data);
        }

        $yaml = '';
        $indentStr = str_repeat('  ', $indent);
        $isSequential = array_keys($data) === range(0, count($data) - 1);

        foreach ($data as $key => $value) {
            if ($isSequential) {
                $yaml .= $indentStr . '-';
                
                if (is_array($value)) {
                    $firstKey = array_key_first($value);
                    if ($firstKey !== null && !is_int($firstKey)) {
                        $yaml .= "\n";
                        $yaml .= $this->arrayToYaml($value, $indent + 1);
                    } else {
                        $yaml .= ' ' . trim($this->arrayToYaml($value, $indent + 1));
                    }
                } else {
                    $yaml .= ' ' . $this->yamlValue($value) . "\n";
                }
            } else {
                $yaml .= $indentStr . $key . ':';
                
                if (is_array($value)) {
                    if (empty($value)) {
                        $yaml .= " {}\n";
                    } else {
                        $yaml .= "\n";
                        $yaml .= $this->arrayToYaml($value, $indent + 1);
                    }
                } else {
                    $yaml .= ' ' . $this->yamlValue($value) . "\n";
                }
            }
        }

        return $yaml;
    }

    /**
     * Formats a scalar value for YAML output.
     *
     * @param  mixed $value
     * @return string
     */
    private function yamlValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $strValue = (string) $value;
        
        // Quote strings that contain special characters or start with special chars
        if (preg_match('/[:\{\}\[\],&*#?|\-<>=!%@`\']/', $strValue) || 
            preg_match('/^\s/', $strValue) ||
            preg_match('/\s$/', $strValue)) {
            return "'" . str_replace("'", "''", $strValue) . "'";
        }

        return $strValue;
    }
}
