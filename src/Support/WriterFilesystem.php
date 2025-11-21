<?php

/**
 * Small helper that centralizes file writes for documentation writers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Support
 */

namespace Equidna\LaravelDocbot\Support;

use Illuminate\Filesystem\Filesystem;

final class WriterFilesystem
{
    public function __construct(private Filesystem $filesystem) {}

    /**
     * Ensures the destination directory exists and writes the provided content.
     * Wraps filesystem exceptions with a RuntimeException containing context.
     *
     * @param string $path
     * @param string $content
     * @param string|null $context
     * @return void
     */
    public function writeFile(string $path, string $content, ?string $context = null): void
    {
        try {
            $this->filesystem->ensureDirectoryExists(dirname($path));
            $this->filesystem->put($path, $content);
        } catch (\Throwable $e) {
            $prefix = $context !== null ? ($context . ': ') : '';

            $msg = sprintf('%sFailed to write documentation to "%s": %s', $prefix, $path, $e->getMessage());

            throw new \RuntimeException($msg, 0, $e);
        }
    }
}
