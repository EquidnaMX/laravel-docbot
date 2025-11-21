<?php

/**
 * Contract for filtering Artisan commands during documentation generation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Contracts
 */

namespace Equidna\LaravelDocbot\Contracts;

use Symfony\Component\Console\Command\Command as ConsoleCommand;

/**
 * Determines whether a command should be excluded.
 */
interface CommandFilter
{
    /**
     * Indicates whether the provided command should be skipped.
     *
     * @param  string                 $name     Artisan command signature name.
     * @param  ConsoleCommand         $command  Console command instance.
     * @return bool
     */
    public function shouldSkip(string $name, ConsoleCommand $command): bool;
}
