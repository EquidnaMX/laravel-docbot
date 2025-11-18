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
use Equidna\LaravelDocbot\Support\ValueHelper;
use Equidna\LaravelDocbot\Contracts\RouteSegmentResolver;
use InvalidArgumentException;
use Equidna\LaravelDocbot\Routing\Support\Sanitizer;

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
                        'safe_key' => $this->sanitizeSegmentKey($key),
                        'prefix' => ValueHelper::stringOrNull($definition['prefix'] ?? null),
                        'host_variable' => ValueHelper::stringOrFallback(
                            $definition['host_variable'] ?? Arr::get($this->defaults, 'host_variable'),
                            'HOST',
                        ),
                        'host_value' => ValueHelper::stringOrFallback(
                            $definition['host_value'] ?? Arr::get($this->defaults, 'host_value'),
                            'https://example.com',
                        ),
                        'domain' => ValueHelper::stringOrNull($definition['domain'] ?? null),
                        'include_middleware' => ValueHelper::stringList($definition['include_middleware'] ?? []),
                        'exclude_middleware' => ValueHelper::stringList($definition['exclude_middleware'] ?? []),
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

        $type = Str::lower(ValueHelper::stringOrFallback($auth['type'] ?? null, 'none'));

        if ($type === 'none') {
            return null;
        }

        $tokenVariable = ValueHelper::stringOrNull(
            $auth['token_variable']
                ?? $definition['token']
                ?? Arr::get($this->defaults, 'auth.token_variable'),
        );

        if ($tokenVariable === null) {
            return null;
        }

        return [
            'type' => $type,
            'header' => ValueHelper::stringOrFallback($auth['header'] ?? null, 'Authorization'),
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
     * Sanitize a segment key to a safe filename component.
     *
     * @param  string $key
     * @return string
     */
    private function sanitizeSegmentKey(string $key): string
    {
        return Sanitizer::filename($key);
    }
}
