<?php

/**
 * Configuration values for the Laravel Docbot package.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

use Equidna\LaravelDocbot\Commands\Filters\ConfigExclusionFilter;
use Equidna\LaravelDocbot\Commands\Writers\MarkdownCommandWriter;
use Equidna\LaravelDocbot\Routing\Collectors\RouterRouteCollector;
use Equidna\LaravelDocbot\Routing\Segments\ConfigSegmentResolver;
use Equidna\LaravelDocbot\Routing\Writers\MarkdownRouteWriter;
use Equidna\LaravelDocbot\Routing\Writers\PostmanRouteWriter;

return [
    'output_dir' => env('DOCBOT_OUTPUT_DIR', base_path('doc')),

    'route_defaults' => [
        'host_variable' => 'HOST',
        'host_value' => env('DOCBOT_ROUTE_HOST', 'https://api.example.com'),
        'auth' => [
            'type' => 'bearer',
            'header' => 'Authorization',
            'token_variable' => 'API_TOKEN',
        ],
    ],

    // Segments for route documentation
    'segments' => [
        // [
        //     'key' => 'api',
        //     'prefix' => 'api/',
        //     'host_variable' => 'API_HOST',
        //     'host_value' => env('DOCBOT_API_HOST', 'https://api.example.com'),
        //     'domain' => null,
        //     'include_middleware' => [],
        //     'exclude_middleware' => [],
        //     'auth' => [
        //         'type' => 'bearer', // bearer|header|none
        //         'header' => 'Authorization',
        //         'token_variable' => 'API_TOKEN',
        //     ],
        // ],
    ],

    'routes' => [
        'collector' => RouterRouteCollector::class,
        'segment_resolver' => ConfigSegmentResolver::class,
        'writers' => [
            MarkdownRouteWriter::class,
            PostmanRouteWriter::class,
        ],
    ],

    'commands' => [
        'filters' => [
            ConfigExclusionFilter::class,
        ],
        'writer' => MarkdownCommandWriter::class,
        'output_filename' => 'project_commands.md',
    ],

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
