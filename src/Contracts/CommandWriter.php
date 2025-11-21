<?php

/**
 * Contract for persisting Artisan command documentation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Contracts
 */

namespace Equidna\LaravelDocbot\Contracts;

/**
 * Defines how command documentation is output.
 */
interface CommandWriter
{
    /**
     * Writes the documentation for the supplied commands.
     *
     * @param  array<string, array{name: string, description: string}> $commands
     * @return void
     */
    public function write(array $commands): void;
}
