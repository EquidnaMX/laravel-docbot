<?php

/**
 * PHPUnit bootstrapper for the Laravel Docbot package.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('base_path')) {
    /**
     * Lightweight base_path() polyfill for package testing.
     */
    function base_path(string $path = ''): string
    {
        $base = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
        $trimmed = ltrim($path, '\\/');

        return $trimmed === '' ? $base : $base . DIRECTORY_SEPARATOR . $trimmed;
    }
}
