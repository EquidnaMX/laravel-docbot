<?php

if (! function_exists('base_path')) {
    /**
     * Minimal stub for static analysis.
     * @param string|null $path
     * @return string
     */
    function base_path(?string $path = null): string
    {
        return __DIR__ . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (! function_exists('config')) {
    /**
     * Minimal stub for static analysis. Returns config value or default.
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config(?string $key = null, $default = null)
    {
        return $default;
    }
}

if (! function_exists('config_path')) {
    /**
     * Minimal stub for static analysis.
     * @param string|null $path
     * @return string
     */
    function config_path(?string $path = null): string
    {
        return base_path($path ? 'config' . DIRECTORY_SEPARATOR . $path : 'config');
    }
}

if (! function_exists('config_path_stub')) {
    // placeholder to avoid name collision; not used
}
