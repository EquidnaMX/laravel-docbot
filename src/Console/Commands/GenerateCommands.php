<?php

/**
 * Console command that emits a Markdown table of custom project commands.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Console\Commands
 */

namespace Equidna\LaravelDocbot\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Equidna\LaravelDocbot\Contracts\CommandFilter;
use Equidna\LaravelDocbot\Contracts\CommandWriter;

/**
 * Lists user-defined Artisan commands, excluding Laravel's built-ins.
 */
class GenerateCommands extends Command
{
    /**
     * Creates the command instance with its writer and filters.
     *
     * @param CommandWriter             $writer   Writer responsible for persisting docs.
     * @param array<int, CommandFilter> $filters  Command filters to apply.
     */
    public function __construct(
        private CommandWriter $writer,
        private array $filters = [],
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected $signature = 'docbot:commands';

    /**
     * {@inheritDoc}
     */
    protected $description = 'List custom Artisan commands defined in the project (excludes built-in commands)';

    /**
     * Generates Markdown documentation for custom project Artisan commands.
     *
     * @return int Exit code (0 on success).
     */
    public function handle(): int
    {
        $app = $this->getApplication();
        if ($app === null) {
            $this->error('Unable to access application commands.');
            $this->writer->write([]);

            return self::FAILURE;
        }

        $allCommands = $app->all();
        $projectCommands = [];

        foreach ($allCommands as $name => $command) {
            if ($this->shouldSkip($name, $command)) {
                continue;
            }

            $projectCommands[$name] = $command;
        }

        if (empty($projectCommands)) {
            $this->info('No custom project commands found.');

            $this->writer->write([]);

            return self::SUCCESS;
        }

        $this->info('Custom Project Artisan Commands:');
        $payload = [];

        foreach ($projectCommands as $name => $command) {
            $description = $command->getDescription();
            $this->line("- <info>{$name}</info>: {$description}");
            $payload[(string) $name] = [
                'name' => (string) $name,
                'description' => $description,
            ];
        }

        $this->writer->write($payload);

        return self::SUCCESS;
    }

    /**
     * Evaluates whether a command should be excluded from documentation.
     *
     * @param  string                                        $name
     * @param  \Symfony\Component\Console\Command\Command $command
     * @return bool
     */
    private function shouldSkip(
        string $name,
        \Symfony\Component\Console\Command\Command $command,
    ): bool {
        foreach ($this->filters as $filter) {
            if ($filter->shouldSkip($name, $command)) {
                return true;
            }
        }

        return Str::startsWith($name, 'vendor:');
    }
}
