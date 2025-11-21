<?php

/**
 * Writes custom Artisan command documentation to Markdown.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Commands\Writers
 */

namespace Equidna\LaravelDocbot\Commands\Writers;

use Equidna\LaravelDocbot\Contracts\CommandWriter;
use Equidna\LaravelDocbot\Routing\Support\Sanitizer;
use Equidna\LaravelDocbot\Support\WriterFilesystem;
use Equidna\LaravelDocbot\Support\PathGuard;

/**
 * Persists the commands table to doc/commands/project_commands.md by default.
 */
final class MarkdownCommandWriter implements CommandWriter
{
    /**
     * @param WriterFilesystem $writerFilesystem Filesystem helper for writes.
     * @param string           $outputDir        Base output directory (defaults to doc/commands).
     * @param string           $filename         Output filename (defaults to project_commands.md).
     */
    public function __construct(
        private WriterFilesystem $writerFilesystem,
        private string $outputDir = '',
        private string $filename = 'project_commands.md',
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function write(array $commands): void
    {
        $directory = $this->outputDir !== ''
            ? $this->outputDir
            : base_path('doc/commands');

        $outputFile = PathGuard::join($directory, $this->filename);

        if (empty($commands)) {
            $this->writerFilesystem->writeFile(
                $outputFile,
                "# Custom Project Artisan Commands\n\nNo custom project commands found.\n",
                'commands'
            );

            return;
        }

        $markdown = "# Custom Project Artisan Commands\n\n";
        $markdown .= "| Command | Description |\n";
        $markdown .= "| ------- | ----------- |\n";

        foreach ($commands as $name => $command) {
            $desc = Sanitizer::cell($command['description'] ?? '');

            $markdown .= sprintf("| `%s` | %s |\n", Sanitizer::cell($name), $desc);
        }

        $this->writerFilesystem->writeFile($outputFile, $markdown, 'commands');
    }

    // Cell sanitization moved to Routing\Support\Sanitizer::cell
}
