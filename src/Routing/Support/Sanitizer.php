<?php

/**
 * Small helper utilities for sanitizing filenames and Markdown table cells.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Routing\Support
 */

namespace Equidna\LaravelDocbot\Routing\Support;

final class Sanitizer
{
    /**
     * Produce a filesystem-safe segment filename from a raw key.
     *
     * Replaces runs of disallowed characters with '-', trims extra
     * '-' characters and falls back to the provided default when the
     * result would be empty or ambiguous ('.' / '..').
     *
     * @param  string|null $raw
     * @param  string      $fallback
     * @return string
     */
    public static function filename(?string $raw, string $fallback = 'unknown'): string
    {
        $value = (string) ($raw ?? '');

        // Default sanitization settings. These may be overridden via
        // the package configuration: config('docbot.sanitization.filename.*').
        $defaultPattern = '/[^A-Za-z0-9._-]+/';
        $defaultReplacement = '-';
        $defaultFallback = $fallback;

        if (function_exists('config')) {
            $patternVal = config('docbot.sanitization.filename.pattern', $defaultPattern);
            $pattern = is_string($patternVal) ? $patternVal : $defaultPattern;

            $replacementVal = config('docbot.sanitization.filename.replacement', $defaultReplacement);
            $replacement = is_string($replacementVal) ? $replacementVal : $defaultReplacement;

            $fallbackVal = config('docbot.sanitization.filename.fallback', $defaultFallback);
            $fallback = is_string($fallbackVal) ? $fallbackVal : $defaultFallback;
        } else {
            $pattern = $defaultPattern;
            $replacement = $defaultReplacement;
        }

        $sanitized = preg_replace($pattern, $replacement, $value);
        $sanitized = is_string($sanitized) ? $sanitized : '';
        $sanitized = trim($sanitized, (string) $replacement);

        if ($sanitized === '' || $sanitized === '.' || $sanitized === '..') {
            return $fallback;
        }

        return $sanitized;
    }

    /**
     * Sanitize arbitrary text for Markdown table cells.
     *
     * Collapses newlines to spaces and escapes pipe and backtick characters.
     *
     * @param  string $text
     * @return string
     */
    public static function cell(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = str_replace('|', '\\|', $text);
        $text = str_replace('`', '\\`', $text);

        return trim($text);
    }
}
