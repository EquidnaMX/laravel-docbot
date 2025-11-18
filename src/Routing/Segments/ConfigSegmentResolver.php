<?php

/**
 * Segment resolver that maps config entries to normalized route segments.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Routing\Segments
 */

namespace Equidna\LaravelDocbot\Routing\Segments;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Equidna\LaravelDocbot\Contracts\RouteSegmentResolver;
use InvalidArgumentException;

/**
 * Applies route defaults, validation, and normalization for each segment.
 */
final class ConfigSegmentResolver implements RouteSegmentResolver
{
    /**
     * @param array<string, mixed> $defaults
     * @param array<int, array<string, mixed>> $definitions
     */
    public function __construct(
        private array $defaults = [],
        private array $definitions = [],
    ) {
        $this->defaults = $this->sanitizeDefaults($defaults);
        $this->definitions = $this->sanitizeDefinitions($definitions);
    }

    /**
     * {@inheritDoc}
     */
    public function segments(): array
    {
        $segments = new Collection(
            array_merge(
                [
                    [
                        'key' => 'web',
                        'auth' => [
                            'type' => 'none',
                        ],
                    ],
                ],
                $this->definitions,
            ),
        );

        return $segments
            ->mapWithKeys(function (array $definition): array {
                $key = $definition['key'] ?? null;

                if (empty($key) || !is_string($key)) {
                    throw new InvalidArgumentException('Each Docbot route segment must define a non-empty "key".');
                }

                return [
                    $key => [
                        'key' => $key,
                        'prefix' => $this->stringOrNull($definition['prefix'] ?? null),
                        'host_variable' => $this->stringOrFallback(
                            $definition['host_variable'] ?? Arr::get($this->defaults, 'host_variable'),
                            'HOST',
                        ),
                        'host_value' => $this->stringOrFallback(
                            $definition['host_value'] ?? Arr::get($this->defaults, 'host_value'),
                            'https://example.com',
                        ),
                        'domain' => $this->stringOrNull($definition['domain'] ?? null),
                        'include_middleware' => $this->stringList($definition['include_middleware'] ?? []),
                        'exclude_middleware' => $this->stringList($definition['exclude_middleware'] ?? []),
                        'auth' => $this->normalizeAuth($definition),
                    ],
                ];
            })
            ->map(function (array $segment): array {
                $segment['include_middleware'] = array_values(array_unique($segment['include_middleware']));
                $segment['exclude_middleware'] = array_values(array_unique($segment['exclude_middleware']));

                return $segment;
            })
            ->all();
    }

    /**
     * Normalizes the auth configuration for a segment.
     *
     * @param  array<string, mixed> $definition
     * @return array<string, string>|null
     */
    private function normalizeAuth(array $definition): ?array
    {
        $auth = $definition['auth'] ?? $this->defaults['auth'] ?? null;

        if (empty($auth) || !is_array($auth)) {
            return null;
        }

        $type = Str::lower($this->stringOrFallback($auth['type'] ?? null, 'none'));

        if ($type === 'none') {
            return null;
        }

        $tokenVariable = $this->stringOrNull(
            $auth['token_variable']
                ?? $definition['token']
                ?? Arr::get($this->defaults, 'auth.token_variable'),
        );

        if ($tokenVariable === null) {
            return null;
        }

        return [
            'type' => $type,
            'header' => $this->stringOrFallback($auth['header'] ?? null, 'Authorization'),
            'token_variable' => $tokenVariable,
        ];
    }

    /**
     * @param  array<string, mixed>                 $defaults
     * @return array<string, mixed>
     */
    private function sanitizeDefaults(array $defaults): array
    {
        if (isset($defaults['auth']) && !is_array($defaults['auth'])) {
            unset($defaults['auth']);
        }

        return $defaults;
    }

    /**
     * @param  array<int, array<string, mixed>> $definitions
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeDefinitions(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $definition) {
            if (is_array($definition)) {
                $normalized[] = $definition;
            }
        }

        return $normalized;
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

    /**
     * @param  mixed  $value
     * @param  string $fallback
     * @return string
     */
    private function stringOrFallback(mixed $value, string $fallback): string
    {
        return $this->stringOrNull($value, $fallback) ?? $fallback;
    }

    /**
     * @param  mixed $value
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            $string = $this->stringOrNull($item);

            if ($string !== null && $string !== '') {
                $strings[] = $string;
            }
        }

        return $strings;
    }
}
