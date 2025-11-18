<?php

/**
 * API documentation and custom command listing configuration.
 *
 * Defines output directories, route segments, and exclusion patterns
 * for the Laravel Docbot package.
 *
 * PHP version 8.0+
 *
 * @package   Equidna\LaravelDocbot
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/laravel-docbot Documentation
 */

return [
    'output_dir' => env('DOCBOT_OUTPUT_DIR', base_path('doc')),

    // Segments for route documentation
    'segments' => [],

    // Namespaces to exclude from custom command listing
    'exclude_namespaces' => [
        'app:',
        'auth:',
        'breeze:',
        'cache:',
        'channel:',
        'completion',
        'config:',
        'db:',
        'debugbar:',
        'event:',
        'foundation:',
        'fortify:',
        'help',
        'horizon:',
        'ide-helper:',
        'install:',
        'jetstream:',
        'key:',
        'lang:',
        'list',
        'mail:',
        'make:',
        'migrate:',
        'model:',
        'notifications:',
        'nova:',
        'octane:',
        'optimize',
        'package:',
        'passport:',
        'policy:',
        'preset:',
        'queue:',
        'route:',
        'sanctum:',
        'scout:',
        'schema:',
        'seed',
        'serve',
        'session:',
        'socialite:',
        'spark:',
        'storage:',
        'stub:',
        'tinker',
        'test',
        'ui:',
        'vendor:',
        'view:',
    ],

    // Commands to exclude from custom command listing
    'exclude_commands' => [
        '_complete',
        'about',
        'clear-compiled',
        'db',
        'docs',
        'down',
        'invoke-serialized-closure',
        'migrate',
        'up',
    ],

];
