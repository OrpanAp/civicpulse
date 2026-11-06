<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

use CivicPulse\Framework\View\Exceptions\ViewException;
use CivicPulse\Framework\View\Exceptions\ViewNotFoundException;

class ViewFinder
{
    private string $viewsPath;

    public function __construct(string $viewsPath)
    {
        $realPath = realpath($viewsPath);

        if ($realPath === false || !is_dir($realPath)) {
            throw new ViewException(
                "The views directory does not exist: {$viewsPath}"
            );
        }

        $this->viewsPath = $realPath;
    }

    public function find(string $name): string
    {
        if (!$this->isValidViewName($name)) {
            throw new ViewNotFoundException(
                "Invalid view name: {$name}"
            );
        }

        $relativePath = str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $name
        );

        $viewsPrefix = rtrim($this->viewsPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        $candidatePath = $viewsPrefix . $relativePath . '.php';

        $realPath = realpath($candidatePath);

        if ($realPath === false || !is_file($realPath)) {
            throw new ViewNotFoundException(
                "View not found: {$name}"
            );
        }

        if (!str_starts_with($realPath, $viewsPrefix)) {
            throw new ViewNotFoundException(
                "View is outside the approved views directory: {$name}"
            );
        }

        return $realPath;
    }

    private function isValidViewName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        return preg_match(
            '/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/',
            $name
        ) === 1;
    }
}
