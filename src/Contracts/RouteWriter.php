<?php

/**
 * Contract for classes that persist route documentation artifacts.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Contracts
 */

namespace Equidna\LaravelDocbot\Contracts;

/**
 * Defines the interface for building a specific documentation format.
 */
interface RouteWriter
{
    /**
     * Returns the unique format key handled by the writer (e.g., markdown, postman).
     *
     * @return string
     */
    public function format(): string;

    /**
     * Persists the documentation output for the provided segment routes.
     *
     * @param  array<string,mixed>              $segment Segment metadata array from the resolver.
     * @param  array<int, array<string,mixed>>  $routes  Normalized route descriptors.
     * @param  string $path    Destination directory for the artifact.
     * @return void
     */
    public function write(
        array $segment,
        array $routes,
        string $path,
    ): void;
}
