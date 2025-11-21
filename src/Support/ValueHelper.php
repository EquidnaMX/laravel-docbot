<?php

/**
 * Utility helpers for normalizing common scalar values and lists.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Support
 */

namespace Equidna\LaravelDocbot\Support;

use Traversable;

/**
 * Provides reusable helpers for casting values to strings or lists of strings.
 */
final class ValueHelper
{
    private function __construct()
    {
        // Static-only helper.
    }

    /**
     * Returns the string representation of a value or the fallback when missing.
     *
     * @param  mixed       $value
     * @param  string|null $fallback
     * @return string|null
     */
    public static function stringOrNull(mixed $value, ?string $fallback = null): ?string
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
     * Returns the string representation of a value or the provided fallback.
     *
     * @param  mixed  $value
     * @param  string $fallback
     * @return string
     */
    public static function stringOrFallback(mixed $value, string $fallback): string
    {
        return self::stringOrNull($value, $fallback) ?? $fallback;
    }

    /**
     * Converts the provided value into a list of non-empty strings.
     *
     * @param  mixed $value
     * @return array<int, string>
     */
    public static function stringList(mixed $value): array
    {
        if (!is_array($value) && !$value instanceof Traversable) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            $string = self::stringOrNull($item);

            if ($string !== null && $string !== '') {
                $strings[] = $string;
            }
        }

        return $strings;
    }
}
