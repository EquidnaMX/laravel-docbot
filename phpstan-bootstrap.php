<?php
// Bootstrap for PHPStan: define minimal Laravel helper stubs used by the package
// This file is only used during static analysis and should not affect runtime.

if (!function_exists('base_path')) {
    /**
     * Minimal stub for base_path used by static analysis.
     * @param string|null $path
     * @return string
     */
    function base_path($path = null)
    {
        $base = __DIR__;
        if ($path === null || $path === '') {
            return $base;
        }
        return rtrim($base . DIRECTORY_SEPARATOR . ltrim((string) $path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('config')) {
    /**
     * Stub for config() helper used by static analysis.
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        return $default;
    }
}

if (!function_exists('config_path')) {
    /**
     * Stub for config_path used by static analysis.
     * @param string|null $path
     * @return string
     */
    function config_path($path = null)
    {
        $cfg = base_path('config');
        if ($path === null || $path === '') {
            return $cfg;
        }
        return rtrim($cfg . DIRECTORY_SEPARATOR . ltrim((string) $path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }
}
