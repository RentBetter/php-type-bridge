<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

final class PhpFileClassLocator
{
    /**
     * @return array<string, string>
     */
    public function classesIn(string $directory): array
    {
        $classes = [];

        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)),
            '/^.+\.php$/i',
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo) {
                continue;
            }

            $file = $fileInfo->getPathname();
            $content = file_get_contents($file);
            if (false === $content) {
                continue;
            }

            $namespace = $this->extractNamespace($content);
            $shortName = $this->extractClassLikeName($content);
            if (null === $shortName) {
                continue;
            }

            $className = null !== $namespace ? $namespace . '\\' . $shortName : $shortName;
            $classes[$className] = $file;
        }

        ksort($classes);

        return $classes;
    }

    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([\w\\\\]+)\s*;/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractClassLikeName(string $content): ?string
    {
        if (preg_match('/(?:final\s+|abstract\s+)?(?:readonly\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
