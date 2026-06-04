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
     * @return array<string, string> fully-qualified class-like name => file path
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

            $className = $this->classNameIn($content);
            if (null !== $className) {
                $classes[$className] = $file;
            }
        }

        ksort($classes);

        return $classes;
    }

    /**
     * Extracts the first declared class-like's fully-qualified name via the PHP
     * tokenizer, so keywords appearing in comments, docblocks, or strings (e.g.
     * "the enum Foo convention") never get misread as a declaration.
     */
    private function classNameIn(string $content): ?string
    {
        $tokens = token_get_all($content);
        $total = \count($tokens);
        $namespace = '';

        for ($i = 0; $i < $total; ++$i) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                continue;
            }

            if (\T_NAMESPACE === $token[0]) {
                $namespace = $this->readName($tokens, $i + 1);

                continue;
            }

            if (\in_array($token[0], [\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM], true)) {
                $previous = $tokens[$i - 1] ?? null;
                if (\T_CLASS === $token[0] && \is_array($previous) && \T_DOUBLE_COLON === $previous[0]) {
                    continue; // `::class`, not a declaration
                }

                $name = $this->readDeclaredName($tokens, $i + 1);
                if (null !== $name) {
                    return '' !== $namespace ? $namespace . '\\' . $name : $name;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readName(array $tokens, int $start): string
    {
        $name = '';
        for ($i = $start, $total = \count($tokens); $i < $total; ++$i) {
            $token = $tokens[$i];
            if (\is_array($token) && \T_WHITESPACE === $token[0]) {
                continue;
            }
            if (\is_array($token) && \in_array($token[0], [\T_STRING, \T_NS_SEPARATOR, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED], true)) {
                $name .= $token[1];

                continue;
            }

            break;
        }

        return $name;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readDeclaredName(array $tokens, int $start): ?string
    {
        for ($i = $start, $total = \count($tokens); $i < $total; ++$i) {
            $token = $tokens[$i];
            if (\is_array($token) && \T_WHITESPACE === $token[0]) {
                continue;
            }
            if (\is_array($token) && \T_STRING === $token[0]) {
                return $token[1];
            }

            return null; // anonymous class or unexpected token
        }

        return null;
    }
}
