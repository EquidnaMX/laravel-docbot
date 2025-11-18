<?php

/**
 * Service provider for Laravel Docbot package.
 *
 * Registers Docbot commands and publishes configuration for Laravel projects.
 * Provides API documentation generation and custom command listing capabilities.
 *
 * PHP version 8.0+
 *
 * @package   Equidna\LaravelDocbot
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/laravel-docbot Documentation
 */

namespace Equidna\LaravelDocbot;

use Illuminate\Support\ServiceProvider;
use Equidna\LaravelDocbot\Console\Commands\GenerateCommands;
use Equidna\LaravelDocbot\Console\Commands\GenerateRoutes;

/**
 * Service provider for Laravel Docbot package.
 *
 * Registers Docbot commands and publishes configuration files.
 */
class LaravelDocbotServiceProvider extends ServiceProvider
{
    /**
     * Register services and merge package configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/docbot.php',
            'docbot'
        );
    }

    /**
     * Bootstrap services, register commands and publishable resources.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    GenerateCommands::class,
                    GenerateRoutes::class,
                ]
            );

            $this->publishes(
                [
                    __DIR__ . '/../config/docbot.php' => config_path('docbot.php'),
                ],
                'laravel-docbot:config'
            );
        }
    }
}
