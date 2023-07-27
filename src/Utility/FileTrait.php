<?php
declare(strict_types=1);

namespace BEdita\ImportTools\Utility;

use BEdita\Core\Filesystem\FilesystemRegistry;

/**
 * Utilities to help reading files from either the local filesystem or an adapter configured in BEdita.
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
        $closerFactory = fn ($resource): callable => function () use ($resource): void {
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
                    trigger_error(sprintf('fopen(%s): falied to open stream', $path), E_USER_ERROR);
                }
            } catch (\Exception $previous) {
                throw new \RuntimeException(sprintf('Cannot open file: %s', $path), 0, $previous);
            }

            return [$fh, $closerFactory($fh)];
        }

        if (!class_exists(FilesystemRegistry::class)) {
            trigger_error(sprintf('Unsupported stream wrapper protocol: %s', $path), E_USER_ERROR);
        }

        $fh = FilesystemRegistry::getMountManager()->readStream($path);

        return [$fh, $closerFactory($fh)];
    }
}
