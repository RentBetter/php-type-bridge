<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use RuntimeException;

final class PhpDocTypeHelper
{
    /**
     * @return array<string, string>
     */
    public function extractPhpStanTypes(string $content): array
    {
        $types = [];

        if (preg_match_all('/@phpstan-type\s+(\w+)\s*=\s*/s', $content, $matches, \PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $name = $matches[1][$index][0];
                $offset = $match[1] + \strlen($match[0]);
                $types[$name] = $this->extractDefinition($content, $offset);
            }
        }

        return $types;
    }

    public function extractVarType(string $docComment): ?string
    {
        if (!preg_match('/@var\s+/s', $docComment, $match, \PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = $match[0][1] + \strlen($match[0][0]);

        return $this->extractDefinition($docComment, $offset);
    }

    /**
     * @param array<string, string> $classFiles
     * @param array<string, list<string>> $shortNameMap
     * @return array<string, ImportedType>
     */
    public function extractImportedTypes(
        string $content,
        string $ownerClass,
        string $srcDir,
        array $classFiles,
        array $shortNameMap,
        DomainGuesser $domainGuesser,
    ): array {
        $imports = [];

        if (!preg_match_all('/@phpstan-import-type\s+(\w+)\s+from\s+([\\\\\w]+)(?:\s+as\s+(\w+))?/', $content, $matches, \PREG_SET_ORDER)) {
            return $imports;
        }

        foreach ($matches as $match) {
            $sourceAlias = $match[1];
            $classRef = $match[2];
            $localAlias = $match[3] ?? $sourceAlias;
            $targetClass = $this->resolveClassReference($classRef, $ownerClass, $classFiles, $shortNameMap);
            $targetTypeName = $this->emittedTypeName($sourceAlias, $targetClass);
            $targetFile = $classFiles[$targetClass] ?? null;
            if (null === $targetFile) {
                throw new RuntimeException(\sprintf('Imported type target "%s" for "%s" was not found.', $targetClass, $ownerClass));
            }

            $imports[$localAlias] = new ImportedType(
                localAlias: $localAlias,
                targetClass: $targetClass,
                targetTypeName: $targetTypeName,
                targetDomain: $domainGuesser->guess($srcDir, $targetFile),
            );
        }

        return $imports;
    }

    /**
     * @param array<string, ImportedType> $imports
     */
    public function resolveImportedNames(ParsedType $type, array $imports): ParsedType
    {
        if ($type instanceof NameRefType) {
            if (isset($imports[$type->name])) {
                return new NameRefType($imports[$type->name]->targetTypeName);
            }

            return $type;
        }

        if ($type instanceof NullableType) {
            return new NullableType(
                inner: $this->resolveImportedNames($type->inner, $imports),
                optional: $type->optional,
            );
        }

        if ($type instanceof ListType) {
            return new ListType($this->resolveImportedNames($type->inner, $imports));
        }

        if ($type instanceof ShapeType) {
            $fields = [];
            foreach ($type->fields as $field) {
                $fields[] = new ShapeField(
                    name: $field->name,
                    type: $this->resolveImportedNames($field->type, $imports),
                    optional: $field->optional,
                );
            }

            return new ShapeType($fields);
        }

        if ($type instanceof IntersectionType) {
            $base = $this->resolveImportedNames($type->base, $imports);
            $extra = $this->resolveImportedNames($type->extra, $imports);
            if (!$base instanceof NameRefType || !$extra instanceof ShapeType) {
                throw new RuntimeException('Resolved intersection type became invalid after import resolution.');
            }

            return new IntersectionType(
                base: $base,
                extra: $extra,
            );
        }

        if ($type instanceof UnionType) {
            return new UnionType(array_map(
                fn(ParsedType $member): ParsedType => $this->resolveImportedNames($member, $imports),
                $type->types,
            ));
        }

        return $type;
    }

    private function extractDefinition(string $content, int $offset): string
    {
        $depth = 0;
        $result = '';
        $length = \strlen($content);

        for ($index = $offset; $index < $length; $index++) {
            $character = $content[$index];

            if ('<' === $character || '{' === $character || '(' === $character) {
                $depth++;
                $result .= $character;
                continue;
            }

            if ('>' === $character || '}' === $character || ')' === $character) {
                $result .= $character;
                $depth = max(0, $depth - 1);
                continue;
            }

            if ("\n" === $character) {
                $lineStart = $index + 1;
                while ($lineStart < $length && \in_array($content[$lineStart], [' ', "\t"], true)) {
                    $lineStart++;
                }

                if ($lineStart < $length && '*' === $content[$lineStart]) {
                    $lineStart++;
                    if ($lineStart < $length && ' ' === $content[$lineStart]) {
                        $lineStart++;
                    }
                    if ($lineStart < $length && ('@' === $content[$lineStart] || '/' === $content[$lineStart])) {
                        break;
                    }

                    $index = $lineStart - 1;
                    $result .= ' ';

                    continue;
                }

                if (0 === $depth) {
                    break;
                }

                $result .= ' ';

                continue;
            }

            if (0 === $depth && '*' === $character && $index + 1 < $length && '/' === $content[$index + 1]) {
                break;
            }

            if (0 === $depth && "\r" === $character) {
                break;
            }

            $result .= $character;
        }

        return trim($result);
    }

    /**
     * @param array<string, string> $classFiles
     * @param array<string, list<string>> $shortNameMap
     */
    private function resolveClassReference(
        string $classRef,
        string $ownerClass,
        array $classFiles,
        array $shortNameMap,
    ): string {
        $trimmed = ltrim($classRef, '\\');
        if (isset($classFiles[$trimmed])) {
            return $trimmed;
        }

        $namespace = $this->namespace($ownerClass);
        if (null !== $namespace) {
            $candidate = $namespace . '\\' . $trimmed;
            if (isset($classFiles[$candidate])) {
                return $candidate;
            }
        }

        $candidates = $shortNameMap[$trimmed] ?? [];
        if (1 === \count($candidates)) {
            return $candidates[0];
        }

        if (\count($candidates) > 1) {
            throw new RuntimeException(\sprintf(
                'Class reference "%s" from "%s" is ambiguous. Use a fully qualified class name.',
                $classRef,
                $ownerClass,
            ));
        }

        throw new RuntimeException(\sprintf(
            'Class reference "%s" from "%s" could not be resolved.',
            $classRef,
            $ownerClass,
        ));
    }

    private function namespace(string $className): ?string
    {
        $position = strrpos($className, '\\');
        if (false === $position) {
            return null;
        }

        return substr($className, 0, $position);
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');
        if (false === $position) {
            return $className;
        }

        return substr($className, $position + 1);
    }

    private function emittedTypeName(string $alias, string $ownerClass): string
    {
        if ('_self' !== $alias) {
            return $alias;
        }

        $shortName = $this->shortName($ownerClass);
        if (enum_exists($ownerClass)) {
            return $shortName . 'Data';
        }

        return $shortName;
    }
}
