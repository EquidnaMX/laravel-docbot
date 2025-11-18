<?php

/**
 * Extracts docblock summaries from controller actions.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Routing\Support
 */

namespace Equidna\LaravelDocbot\Routing\Support;

use ReflectionMethod;

/**
 * Provides reusable description parsing for route doc generation.
 */
final class RouteDescriptionExtractor
{
    /**
     * Returns the first meaningful sentence from a controller docblock.
     *
     * @param  string $action Controller@method notation.
     * @return string
     */
    public function extract(string $action): string
    {
        /** @var array<string, string> $cache */
        static $cache = [];

        if (isset($cache[$action])) {
            return $cache[$action];
        }

        if (!str_contains($action, '@')) {
            $cache[$action] = '';

            return '';
        }

        [$class, $method] = explode('@', $action, 2) + [1 => ''];

        if (!class_exists($class) || !method_exists($class, $method)) {
            $cache[$action] = '';

            return '';
        }

        $reflection = new ReflectionMethod($class, $method);
        $comment = $reflection->getDocComment();

        if ($comment === false) {
            $cache[$action] = '';

            return '';
        }

        $lines = preg_split('/\r?\n/', trim($comment));
        if (!is_array($lines)) {
            $lines = [];
        }
        $summary = [];

        foreach ($lines as $line) {
            $line = trim($line, "/* \t\r\n");

            if ($line === '' || str_starts_with($line, '@')) {
                break;
            }

            $summary[] = $line;

            if (str_ends_with($line, '.')) {
                break;
            }
        }

        $result = trim(implode(' ', $summary));
        $cache[$action] = $result;

        return $result;
    }
}
