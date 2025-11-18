<?php

/**
 * Contract for services that describe Laravel routes for documentation purposes.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Contracts
 */

namespace Equidna\LaravelDocbot\Contracts;

/**
 * Provides route metadata that downstream writers can consume.
 */
interface RouteCollector
{
    /**
     * Returns a normalized array of route metadata.
     *
     * Each entry MUST contain the following keys:
     * - methods: string[]
     * - uri: non-empty-string
     * - name: string|null
     * - action: class-string|string (callable identifier)
     * - middleware: string[]
     * - domain: string|null
     * - path_parameters: string[]
     *
     * @return array<int, array<string, mixed>>
     */
    public function collect(): array;
}
