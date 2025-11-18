<?php

/**
 * Provides lightweight helper polyfills for package unit tests.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Tests\Support
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!function_exists('base_path')) {
    /**
     * Returns the repository root path or an appended child segment.
     *
     * @param  string $path  Optional child path relative to the repository root.
     * @return string        Absolute path to the repository root or provided child directory.
     */
    function base_path(string $path = ''): string
    {
        static $base = null;

        if ($base === null) {
            $root = realpath(__DIR__ . '/../..');
            $base = $root !== false ? $root : dirname(__DIR__, 2);
        }

        $trimmed = ltrim($path, '\\/');

        return $trimmed === '' ? $base : $base . DIRECTORY_SEPARATOR . $trimmed;
    }
}
