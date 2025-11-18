<?php

/**
 * Guards Docbot's filesystem operations against path traversal outside the project root.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Support
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\LaravelDocbot\Support;

use RuntimeException;

/**
 * Provides helpers that normalize configured paths and enforce confinement to base_path().
 */
final class PathGuard
{
    /**
     * Cached canonical representation of the application's base path.
     */
    private static ?string $basePath = null;

    private function __construct()
    {
        // Static-only helper.
    }

    /**
     * Resolves the configured Docbot output directory and enforces confinement to the project root.
     *
     * @param  mixed  $configuredPath  Raw configuration value (docbot.output_dir).
     * @param  string $contextKey      Configuration key reference for error messages.
     * @return string
     */
    public static function resolveOutputRoot(mixed $configuredPath, string $contextKey = 'docbot.output_dir'): string
    {
        $candidate = ValueHelper::stringOrNull($configuredPath);
        $path = $candidate !== null ? trim($candidate) : '';

        if ($path === '') {
            $path = base_path('doc');
        }

        $target = self::isAbsolute($path)
            ? $path
            : base_path($path);

        $normalizedTarget = self::canonicalize($target);
        $base = self::baseRoot();

        self::assertWithinBase($normalizedTarget, $base, $contextKey);

        return self::toNativeSeparators($normalizedTarget);
    }

    /**
     * Joins path segments using normalized separators.
     *
     * @param  string        $root
     * @param  string ...$segments
     * @return string
     */
    public static function join(string $root, string ...$segments): string
    {
        $normalized = self::canonicalize($root);

        foreach ($segments as $segment) {
            $value = ValueHelper::stringOrNull($segment);

            if ($value === null) {
                continue;
            }

            $trimmed = trim(str_replace('\\', '/', $value), '/');

            if ($trimmed === '') {
                continue;
            }

            $normalized = rtrim($normalized, '/') . '/' . $trimmed;
        }

        return self::toNativeSeparators($normalized);
    }

    /**
     * Returns the canonicalized base_path() once per request for reuse.
     *
     * @return string
     */
    private static function baseRoot(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $root = base_path();
        $real = realpath($root);

        self::$basePath = self::canonicalize($real !== false ? $real : $root);

        return self::$basePath;
    }

    /**
     * Canonicalizes a filesystem path without requiring it to exist on disk.
     *
     * @param  string $path
     * @return string
     */
    private static function canonicalize(string $path): string
    {
        if ($path === '') {
            return '.';
        }

        $path = str_replace('\\', '/', $path);
        $prefix = '';
        $drive = '';

        if (str_starts_with($path, '//')) {
            $prefix = '//';
            $path = substr($path, 2);
        } elseif (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $drive = strtoupper($path[0]) . ':';
            $path = substr($path, 2);
            $path = ltrim($path, '/');
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $segments = explode('/', $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (!empty($resolved) && end($resolved) !== '..') {
                    array_pop($resolved);

                    continue;
                }

                if ($prefix === '' && $drive === '') {
                    $resolved[] = '..';
                }

                continue;
            }

            $resolved[] = $segment;
        }

        $normalized = implode('/', $resolved);

        if ($drive !== '') {
            return $normalized === '' ? $drive . '/' : $drive . '/' . $normalized;
        }

        if ($prefix !== '') {
            if ($prefix === '//' && $normalized !== '') {
                return '//' . $normalized;
            }

            return $normalized === '' ? $prefix : $prefix . $normalized;
        }

        return $normalized === '' ? '.' : $normalized;
    }

    /**
     * Ensures a candidate path remains inside the canonical base path.
     *
     * @param  string $candidate
     * @param  string $base
     * @param  string $contextKey
     * @return void
     */
    private static function assertWithinBase(string $candidate, string $base, string $contextKey): void
    {
        $normalizedCandidate = self::normalizeCase($candidate);
        $normalizedBase = self::normalizeCase($base);

        if ($normalizedCandidate === $normalizedBase) {
            return;
        }

        $prefix = $normalizedBase === '/' ? '/' : $normalizedBase . '/';

        if (str_starts_with($normalizedCandidate, $prefix)) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Invalid %s value "%s". Paths must remain within %s to mitigate path traversal risks.',
                $contextKey,
                $candidate,
                $base,
            ),
        );
    }

    /**
     * Determines whether the provided path is absolute for the current platform.
     *
     * @param  string $path
     * @return bool
     */
    private static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, '\\') || str_starts_with($path, '//')) {
            return true;
        }

        if (str_starts_with($path, '/')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\/]/', $path) === 1;
    }

    /**
     * Normalizes casing for prefix comparisons on Windows.
     *
     * @param  string $path
     * @return string
     */
    private static function normalizeCase(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return strtolower($path);
        }

        return $path;
    }

    /**
     * Converts "/" separators to the platform-specific variant when needed.
     *
     * @param  string $path
     * @return string
     */
    private static function toNativeSeparators(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return $path;
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
