<?php
declare(strict_types=1);

namespace BEdita\ImportTools\Utility;

use BEdita\Core\Filesystem\FilesystemRegistry;
use RuntimeException;
use Throwable;

/**
 * Utilities to help reading files from either the local filesystem or an adapter configured in BEdita.
 *
 * This provides `readFileStream` method to open "read-only" file stream (you can use local filesystem or adapter).
 *
 * Usage example:
 * ```php
 * use BEdita\ImportTools\Utility\FileTrait;
 *
 * class MyImporter
 * {
 *     use FileTrait;
 *
 *     public function read(string $file): void
 *     {
 *         [$fh, $close] = $this->readFileStream($path);
 *
 *         try {
 *             flock($fh, LOCK_SH);
 *             // do your stuff
 *         } finally {
 *             $close();
 *         }
 *     }
 * }
 * ```
 */
trait FileTrait
{
    /**
     * Open read-only file stream. Possible sources are:
     *  - `-` for STDIN
     *  - local paths
     *  - any URL supported by a registered PHP stream wrapper — notably, remote URLs via HTTP(S)
     *  - (if `bedita/core` is available) any mountpoint registered in `FilesystemRegistry`
     *
     * @param string $path Path to open file from.
     * @return array{resource, callable(): void}
     */
    protected static function readFileStream(string $path): array
    {
        /**
         * Create a function to close the requested resource.
         *
         * @param resource|null $fh Resource to be closed.
         * @return callable(): void Function to be used for closing the resource.
         */
        $closerFactory = fn($resource): callable => function () use ($resource): void {
            if (is_resource($resource)) {
                fclose($resource);
            }
        };

        if ($path === '-') {
            return [STDIN, $closerFactory(null)]; // We don't really want to close STDIN.
        }

        if (!str_contains($path, '://') || in_array(explode('://', $path, 2)[0], stream_get_wrappers(), true)) {
            try {
                $fh = fopen($path, 'rb');
                if ($fh === false) {
                    throw new RuntimeException(sprintf('fopen(%s): failed to open stream', $path));
                }
            } catch (Throwable $previous) {
                throw new RuntimeException(sprintf('Cannot open file: %s', $path), 0, $previous);
            }

            return [$fh, $closerFactory($fh)];
        }

        if (!class_exists(FilesystemRegistry::class)) {
            throw new RuntimeException(sprintf('Unsupported stream wrapper protocol: %s', $path));
        }

        $fh = FilesystemRegistry::getMountManager()->readStream($path);

        return [$fh, $closerFactory($fh)];
    }
}
