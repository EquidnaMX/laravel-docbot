<?php

/**
 * Contract for resolving Docbot route segments (group configuration).
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Contracts
 */

namespace Equidna\LaravelDocbot\Contracts;

/**
 * Resolves configured route segments including applied defaults.
 */
interface RouteSegmentResolver
{
    /**
     * Returns the configured route segments keyed by their identifier.
     *
     * @return array<string, array<string, mixed>>
     */
    public function segments(): array;
}
