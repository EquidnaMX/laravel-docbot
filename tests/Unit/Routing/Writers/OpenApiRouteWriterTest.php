<?php

/**
 * Unit tests for the OpenApiRouteWriter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Tests\Unit\Routing\Writers
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Tests\Unit\Routing\Writers;

use Equidna\LaravelDocbot\Routing\Writers\OpenApiRouteWriter;
use Equidna\LaravelDocbot\Routing\Support\RouteDescriptionExtractor;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * Verifies OpenApiRouteWriter generates valid OpenAPI 3.0 specifications.
 */
final class OpenApiRouteWriterTest extends TestCase
{
    /**
     * Ensures the writer reports the correct format identifier.
     *
     * @return void
     */
    public function testFormatReturnsOpenApi(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $descriptions = $this->createMock(RouteDescriptionExtractor::class);

        $writer = new OpenApiRouteWriter($filesystem, $descriptions);

        $this->assertSame('openapi', $writer->format());
    }

    /**
     * Ensures the writer generates a YAML file with OpenAPI structure.
     *
     * @return void
     */
    public function testWriteCreatesYamlFileWithOpenApiStructure(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $descriptions = $this->createMock(RouteDescriptionExtractor::class);

        $descriptions->method('extract')->willReturn('Test endpoint description');

        $capturedContent = null;
        $capturedPath = null;

        $filesystem->expects($this->once())
            ->method('ensureDirectoryExists')
            ->with($this->callback(function ($dir) {
                return is_string($dir);
            }));

        $filesystem->expects($this->once())
            ->method('put')
            ->with(
                $this->callback(function ($path) use (&$capturedPath) {
                    $capturedPath = $path;
                    return str_ends_with($path, '.yaml');
                }),
                $this->callback(function ($content) use (&$capturedContent) {
                    $capturedContent = $content;
                    return is_string($content);
                })
            );

        $writer = new OpenApiRouteWriter($filesystem, $descriptions);

        $segment = [
            'key' => 'api',
            'safe_key' => 'api',
            'host_value' => 'https://api.example.com',
            'auth' => [
                'type' => 'bearer',
                'token_variable' => 'API_TOKEN',
                'header' => 'Authorization',
            ],
        ];

        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'users',
                'name' => 'users.index',
                'action' => 'App\Http\Controllers\UserController@index',
                'middleware' => [],
                'domain' => null,
                'path_parameters' => [],
            ],
            [
                'methods' => ['GET'],
                'uri' => 'users/{id}',
                'name' => 'users.show',
                'action' => 'App\Http\Controllers\UserController@show',
                'middleware' => [],
                'domain' => null,
                'path_parameters' => ['id'],
            ],
        ];

        $writer->write($segment, $routes, '/tmp/test');

        $this->assertNotNull($capturedContent);
        $this->assertStringContainsString('openapi: 3.0.0', $capturedContent);
        $this->assertStringContainsString('api API', $capturedContent);
        $this->assertStringContainsString('/users:', $capturedContent);
        $this->assertStringContainsString('/users/{id}:', $capturedContent);
        $this->assertStringContainsString('bearerAuth', $capturedContent);
    }

    /**
     * Ensures the writer handles routes without authentication.
     *
     * @return void
     */
    public function testWriteHandlesRoutesWithoutAuth(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $descriptions = $this->createMock(RouteDescriptionExtractor::class);

        $descriptions->method('extract')->willReturn('');

        $capturedContent = null;

        $filesystem->method('ensureDirectoryExists')->willReturn(true);
        $filesystem->expects($this->once())
            ->method('put')
            ->with(
                $this->anything(),
                $this->callback(function ($content) use (&$capturedContent) {
                    $capturedContent = $content;
                    return is_string($content);
                })
            );

        $writer = new OpenApiRouteWriter($filesystem, $descriptions);

        $segment = [
            'key' => 'public',
            'safe_key' => 'public',
            'host_value' => 'https://example.com',
        ];

        $routes = [
            [
                'methods' => ['GET'],
                'uri' => 'health',
                'name' => 'health.check',
                'action' => null,
                'middleware' => [],
                'domain' => null,
                'path_parameters' => [],
            ],
        ];

        $writer->write($segment, $routes, '/tmp/test');

        $this->assertNotNull($capturedContent);
        $this->assertStringContainsString('openapi: 3.0.0', $capturedContent);
        $this->assertStringContainsString('public API', $capturedContent);
        $this->assertStringNotContainsString('securitySchemes', $capturedContent);
    }

    /**
     * Ensures the writer includes request body for POST/PUT/PATCH methods.
     *
     * @return void
     */
    public function testWriteIncludesRequestBodyForMutatingMethods(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $descriptions = $this->createMock(RouteDescriptionExtractor::class);

        $descriptions->method('extract')->willReturn('');

        $capturedContent = null;

        $filesystem->method('ensureDirectoryExists')->willReturn(true);
        $filesystem->expects($this->once())
            ->method('put')
            ->with(
                $this->anything(),
                $this->callback(function ($content) use (&$capturedContent) {
                    $capturedContent = $content;
                    return is_string($content);
                })
            );

        $writer = new OpenApiRouteWriter($filesystem, $descriptions);

        $segment = [
            'key' => 'api',
            'safe_key' => 'api',
            'host_value' => 'https://api.example.com',
        ];

        $routes = [
            [
                'methods' => ['POST'],
                'uri' => 'users',
                'name' => 'users.store',
                'action' => null,
                'middleware' => [],
                'domain' => null,
                'path_parameters' => [],
            ],
        ];

        $writer->write($segment, $routes, '/tmp/test');

        $this->assertNotNull($capturedContent);
        $this->assertStringContainsString('requestBody', $capturedContent);
        $this->assertStringContainsString('application/json', $capturedContent);
    }
}
