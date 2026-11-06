<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View\Compiler;

use CivicPulse\Framework\View\Exceptions\ViewException;
use Throwable;

final class CompiledViewCache
{
    private string $cachePath;

    public function __construct(
        string $cachePath,
        private Compiler $compiler
    ) {
        if (
            !is_dir($cachePath)
            && !mkdir($cachePath, 0775, true)
            && !is_dir($cachePath)
        ) {
            throw new ViewException(
                "Unable to create compiled view cache directory: {$cachePath}"
            );
        }

        $realPath = realpath($cachePath);

        if ($realPath === false || !is_dir($realPath)) {
            throw new ViewException(
                "Invalid compiled view cache directory: {$cachePath}"
            );
        }

        if (!is_writable($realPath)) {
            throw new ViewException(
                "Compiled view cache directory is not writable: {$realPath}"
            );
        }

        $this->cachePath = $realPath;
    }

    public function compile(string $sourcePath): string
    {
        $realSourcePath = realpath($sourcePath);

        if ($realSourcePath === false || !is_file($realSourcePath)) {
            throw new ViewException(
                "View source file does not exist: {$sourcePath}"
            );
        }

        $source = file_get_contents($realSourcePath);

        if ($source === false) {
            throw new ViewException(
                "Unable to read view source file: {$realSourcePath}"
            );
        }

        $compilerFile = (new \ReflectionClass($this->compiler))
            ->getFileName();

        if ($compilerFile === false) {
            throw new ViewException(
                'Unable to locate compiler source file.'
            );
        }

        $compilerFingerprint = hash_file(
            'sha256',
            $compilerFile
        );

        if ($compilerFingerprint === false) {
            throw new ViewException(
                'Unable to calculate compiler fingerprint.'
            );
        }

        // The cache key changes when the source or compiler changes.
        $cacheKey = hash(
            'sha256',
            $realSourcePath
                . "\0"
                . hash('sha256', $source)
                . "\0"
                . $compilerFingerprint
        );

        $compiledPath = $this->cachePath
            . DIRECTORY_SEPARATOR
            . $cacheKey
            . '.php';

        // Reuse an existing compiled version.
        if (is_file($compiledPath)) {
            return $compiledPath;
        }

        // Compile the source before writing anything to the cache.
        $compiled = $this->compiler->compile($source);

        $temporaryPath = $this->cachePath
            . DIRECTORY_SEPARATOR
            . '.'
            . $cacheKey
            . '.'
            . bin2hex(random_bytes(8))
            . '.tmp';

        try {
            $writtenBytes = file_put_contents(
                $temporaryPath,
                $compiled,
                LOCK_EX
            );

            if (
                $writtenBytes === false
                || $writtenBytes !== strlen($compiled)
            ) {
                throw new ViewException(
                    'Failed to write the complete compiled view.'
                );
            }

            // Another process may have created this cache file.
            if (is_file($compiledPath)) {
                if (!unlink($temporaryPath)) {
                    throw new ViewException(
                        'Unable to remove temporary compiled view file.'
                    );
                }

                return $compiledPath;
            }

            // Rename within the same directory for atomic publication.
            if (!rename($temporaryPath, $compiledPath)) {
                // A concurrent process may have won the race.
                if (is_file($compiledPath)) {
                    if (!unlink($temporaryPath)) {
                        throw new ViewException(
                            'Unable to remove temporary compiled view file.'
                        );
                    }

                    return $compiledPath;
                }

                throw new ViewException(
                    'Unable to publish the compiled view cache file.'
                );
            }

            return $compiledPath;
        } catch (Throwable $exception) {
            // Remove an incomplete temporary file if one remains.
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw new ViewException(
                "Failed to cache compiled view: {$realSourcePath}",
                previous: $exception
            );
        }
    }
}
