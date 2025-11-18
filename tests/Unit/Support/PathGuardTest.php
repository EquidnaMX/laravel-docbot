<?php

/**
 * Unit tests for the PathGuard helper.
 *
 * PHP 8.1+
 *
 * @package   Equidna\LaravelDocbot\Tests\Unit\Support
 * @author    EquidnaMX <info@equidna.mx>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Tests\Unit\Support;

use Equidna\LaravelDocbot\Support\PathGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies PathGuard canonicalizes paths and enforces confinement within base_path().
 */
final class PathGuardTest extends TestCase
{
    /**
     * Ensures the helper falls back to base_path('doc') when the config value is missing.
     *
     * @return void  Method assertions verify the default doc directory.
     */
    public function testResolveOutputRootDefaultsToDocDirectory(): void
    {
        $result = PathGuard::resolveOutputRoot(null);

        $this->assertSame(base_path('doc'), $result);
    }

    /**
     * Ensures relative child directories under the project root are accepted.
     *
     * @return void  Method assertions verify safe relative paths are normalized.
     */
    public function testResolveOutputRootAcceptsRelativeChildDirectory(): void
    {
        $result = PathGuard::resolveOutputRoot(' storage/docs ');

        $this->assertSame(base_path('storage/docs'), $result);
    }

    /**
     * Ensures absolute descendants that remain within the project root stay accepted.
     *
     * @return void  Method assertions verify absolute descendant paths are canonicalized.
     */
    public function testResolveOutputRootAcceptsAbsoluteDescendantPath(): void
    {
        $absolute = base_path('doc') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'doc' . DIRECTORY_SEPARATOR . 'custom';
        $result = PathGuard::resolveOutputRoot($absolute);

        $this->assertSame(base_path('doc/custom'), $result);
    }

    /**
     * Asserts traversals that would escape the project root are rejected.
     *
     * @return void  Method assertions verify rejection of traversal attempts.
     */
    public function testResolveOutputRootRejectsTraversalOutsideBase(): void
    {
        $this->expectException(RuntimeException::class);

        PathGuard::resolveOutputRoot('../outside');
    }

    /**
     * Ensures absolute paths targeting directories outside the repository root are rejected.
     *
     * @return void  Method assertions verify absolute outside paths throw exceptions.
     */
    public function testResolveOutputRootRejectsAbsolutePathOutsideBase(): void
    {
        $this->expectException(RuntimeException::class);

        $outside = base_path() . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'docbot-outside';

        PathGuard::resolveOutputRoot($outside);
    }

    /**
     * Ensures runtime error messages mention the provided context key for faster debugging.
     *
     * @return void  Method assertions verify the context key is echoed in exception messages.
     */
    public function testResolveOutputRootIncludesContextKeyInExceptionMessage(): void
    {
        $contextKey = 'docbot.custom_output';

        try {
            PathGuard::resolveOutputRoot(
                '../outside',
                $contextKey,
            );

            $this->fail('Expected runtime exception for traversal outside project root.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($contextKey, $exception->getMessage());
        }
    }

    /**
     * Ensures join normalizes segments and preserves native separators.
     *
     * @return void  Method assertions verify join normalizes separators and whitespace.
     */
    public function testJoinNormalizesSegments(): void
    {
        $root = PathGuard::resolveOutputRoot('doc');
        $result = PathGuard::join(
            $root,
            '\\routes\\',
            'api ',
        );

        $this->assertSame(base_path('doc/routes/api'), $result);
    }

    /**
     * Ensures join skips empty or whitespace-only fragments without modifying the root path.
     *
     * @return void  Method assertions verify empty segments are ignored.
     */
    public function testJoinSkipsEmptySegments(): void
    {
        $root = base_path('doc');
        $result = PathGuard::join(
            $root,
            '',
            '   ',
            DIRECTORY_SEPARATOR,
        );

        $this->assertSame($root, $result);
    }
}
