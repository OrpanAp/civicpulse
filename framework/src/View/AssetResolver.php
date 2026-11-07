<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

use CivicPulse\Framework\View\Exceptions\ViewException;

/**
 * Resolves logical asset names into validated public files and URLs.
 */
final class AssetResolver
{
    private string $publicPath;

    private string $stylesPath;

    private string $scriptsPath;

    private string $baseUrl;

    public function __construct(
        string $publicPath,
        string $baseUrl = ''
    ) {
        $resolvedPublicPath = realpath($publicPath);

        if (
            $resolvedPublicPath === false
            || !is_dir($resolvedPublicPath)
        ) {
            throw new ViewException(
                'The public directory does not exist.'
            );
        }

        $this->publicPath = $resolvedPublicPath;

        $this->stylesPath = $this->publicPath
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'css';

        $this->scriptsPath = $this->publicPath
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'js';

        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    /**
     * Resolve a logical resource path into a public URL.
     *
     * @param 'styles'|'scripts' $type
     */
    public function resolve(
        string $type,
        string $path
    ): string {
        $directory = match ($type) {
            'styles' => $this->stylesPath,
            'scripts' => $this->scriptsPath,
            default => throw new ViewException(
                'Unsupported asset type: ' . $type
            ),
        };

        $this->validateLogicalPath($path);

        // Resolve the asset directory itself first.
        $resolvedDirectory = realpath($directory);

        if (
            $resolvedDirectory === false
            || !is_dir($resolvedDirectory)
        ) {
            throw new ViewException(
                'The asset directory does not exist.'
            );
        }

        // The CSS or JS directory must remain inside public/.
        $this->assertInsideDirectory(
            $resolvedDirectory,
            $this->publicPath
        );

        $extension = $type === 'styles' ? '.css' : '.js';

        $relativePath = $path . $extension;

        $candidatePath = $resolvedDirectory
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );

        $resolvedPath = realpath($candidatePath);

        if (
            $resolvedPath === false
            || !is_file($resolvedPath)
        ) {
            throw new ViewException(
                'Asset file does not exist: ' . $relativePath
            );
        }

        $this->assertInsideDirectory(
            $resolvedPath,
            $directory
        );

        return $this->buildUrl(
            $type,
            $relativePath
        );
    }

    private function validateLogicalPath(string $path): void
    {
        if (
            $path === ''
            || preg_match(
                '/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/',
                $path
            ) !== 1
        ) {
            throw new ViewException(
                'Invalid logical asset path: ' . $path
            );
        }
    }

    private function assertInsideDirectory(
        string $resolvedPath,
        string $directory
    ): void {
        $resolvedDirectory = realpath($directory);

        if (
            $resolvedDirectory === false
            || !is_dir($resolvedDirectory)
        ) {
            throw new ViewException(
                'The asset directory does not exist.'
            );
        }

        $prefix = rtrim(
            $resolvedDirectory,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR;

        $normalizedFile = $this->normalizeFilesystemPath(
            $resolvedPath
        );

        $normalizedPrefix = $this->normalizeFilesystemPath(
            $prefix
        );

        if (
            DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp(
                $normalizedFile,
                $normalizedPrefix,
                strlen($normalizedPrefix)
            ) !== 0
            : !str_starts_with(
                $normalizedFile,
                $normalizedPrefix
            )
        ) {
            throw new ViewException(
                'Asset path escapes its approved directory.'
            );
        }
    }

    private function normalizeFilesystemPath(string $path): string
    {
        return rtrim(
            str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $path
            ),
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);

        if ($baseUrl === '') {
            return '';
        }

        if (
            str_starts_with($baseUrl, '//')
            || preg_match(
                '~\A(?:https?://[a-zA-Z0-9.-]+(?::[0-9]+)?|/[a-zA-Z0-9._/-]*)\z~',
                $baseUrl
            ) !== 1
        ) {
            throw new ViewException(
                'Invalid asset base URL.'
            );
        }

        if (str_contains($baseUrl, '..')) {
            throw new ViewException(
                'Asset base URL cannot contain path traversal.'
            );
        }

        return rtrim($baseUrl, '/');
    }

    private function buildUrl(
        string $type,
        string $relativePath
    ): string {
        $urlPath = '/assets/'
            . ($type === 'styles' ? 'css/' : 'js/')
            . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        return $this->baseUrl . $urlPath;
    }
}
