<?php

/**
 * Command filter that relies on config-driven exclusion lists.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Commands\Filters
 */

namespace Equidna\LaravelDocbot\Commands\Filters;

use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Illuminate\Support\Str;
use Equidna\LaravelDocbot\Contracts\CommandFilter;

/**
 * Excludes commands based on namespace prefixes or explicit names.
 */
final class ConfigExclusionFilter implements CommandFilter
{
    /**
     * Creates a new filter instance.
     *
     * @param array<int, string> $namespaces
     * @param array<int, string> $commands
     */
    public function __construct(
        private array $namespaces = [],
        private array $commands = [],
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function shouldSkip(
        string $name,
        ConsoleCommand $command,
    ): bool {
        foreach ($this->namespaces as $prefix) {
            if (Str::startsWith($name, $prefix)) {
                return true;
            }
        }

        return in_array($name, $this->commands, true);
    }
}
