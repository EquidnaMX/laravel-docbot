<?php

/**
 * Writes custom Artisan command documentation to Markdown.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Commands\Writers
 */

namespace Equidna\LaravelDocbot\Commands\Writers;

use Illuminate\Filesystem\Filesystem;
use Equidna\LaravelDocbot\Contracts\CommandWriter;
use Equidna\LaravelDocbot\Routing\Support\Sanitizer;

/**
 * Persists the commands table to doc/commands/project_commands.md by default.
 */
final class MarkdownCommandWriter implements CommandWriter
{
    /**
     * @param Filesystem $filesystem Filesystem instance used for writes.
     * @param string     $outputDir  Base output directory (defaults to doc/commands).
     * @param string     $filename   Output filename (defaults to project_commands.md).
     */
    public function __construct(
        private Filesystem $filesystem,
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

        $outputFile = rtrim($directory, '/\\') . '/' . $this->filename;

        try {
            $this->filesystem->ensureDirectoryExists($directory);

            if (empty($commands)) {
                $this->filesystem->put(
                    $outputFile,
                    "# Custom Project Artisan Commands\n\nNo custom project commands found.\n",
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

            $this->filesystem->put($outputFile, $markdown);
        } catch (\Throwable $e) {
            $msg = sprintf('Failed to write command documentation to "%s": %s', $outputFile, $e->getMessage());

            throw new \RuntimeException($msg, 0, $e);
        }
    }

    // Cell sanitization moved to Routing\Support\Sanitizer::cell
}
