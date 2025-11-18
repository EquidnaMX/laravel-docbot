<?php

/**
 * Resolves configured RouteWriter implementations from the container.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Routing
 */

namespace Equidna\LaravelDocbot\Routing;

use Illuminate\Contracts\Container\Container;
use Equidna\LaravelDocbot\Contracts\RouteWriter;
use InvalidArgumentException;

/**
 * Lazily instantiates configured route writers.
 */
final class RouteWriterManager
{
    /**
     * @var array<string, RouteWriter>
     */
    private array $cache = [];

    /**
     * @param Container              $container    Application container.
     * @param array<int, class-string<RouteWriter>> $writerClasses Configured writer class names.
     */
    public function __construct(
        private Container $container,
        private array $writerClasses,
    ) {
        //
    }

    /**
     * Returns a keyed list of instantiated route writers (keyed by format).
     *
     * @return array<string, RouteWriter>
     */
    public function all(): array
    {
        if (!empty($this->cache)) {
            return $this->cache;
        }

        foreach ($this->writerClasses as $class) {
            $writer = $this->container->make($class);

            if (!$writer instanceof RouteWriter) {
                throw new InvalidArgumentException($class . ' must implement ' . RouteWriter::class);
            }

            $this->cache[$writer->format()] = $writer;
        }

        return $this->cache;
    }
}
