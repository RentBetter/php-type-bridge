<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\ClassConstantType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Parser\UnionType;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Reads the PHPDoc tags TypeBridge is built on.
 *
 * Docblocks are parsed with phpstan/phpdoc-parser and PHP source is split with the tokeniser,
 * rather than matched with regular expressions. The regexes this replaces had to re-implement
 * bracket balancing and the stripping of leading `*` continuations by hand, and could not see
 * a tag that wrapped across lines in a way they did not anticipate.
 *
 * Types come back out as strings because that is what TypeBridge's own PhpDocShapeParser
 * consumes; the parser is used to *find* and delimit them correctly, not to replace the shape
 * parser. A type is therefore returned in phpdoc-parser's normalised spelling — the same type,
 * with its own whitespace and any trailing comma dropped.
 */
final class PhpDocTypeHelper
{
    private readonly Lexer $lexer;
    private readonly PhpDocParser $parser;

    public function __construct()
    {
        $config = new ParserConfig(usedAttributes: []);
        $constExprParser = new ConstExprParser($config);

        $this->lexer = new Lexer($config);
        $this->parser = new PhpDocParser($config, new TypeParser($config, $constExprParser), $constExprParser);
    }

    /**
     * @return array<string, string>
     */
    public function extractPhpStanTypes(string $content): array
    {
        $types = [];

        foreach ($this->docComments($content) as $docComment) {
            foreach ($this->parse($docComment)->getTypeAliasTagValues() as $tag) {
                $types[$tag->alias] = (string)$tag->type;
            }
        }

        return $types;
    }

    public function extractVarType(string $docComment): ?string
    {
        foreach ($this->parse($docComment)->getVarTagValues() as $tag) {
            return (string)$tag->type;
        }

        return null;
    }

    /**
     * A property's documented type, read where PHPStan reads it: its own `@var`, or — for a
     * promoted constructor property without one — the constructor's `@param` for it. Both are
     * how a promoted property is typed, so reading only `@var` would miss half of them and
     * fall back to the native `array`.
     */
    public function extractPropertyType(ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();
        if (false !== $docComment && null !== $type = self::nonEmpty($this->extractVarType($docComment))) {
            return $type;
        }

        if (!$property->isPromoted()) {
            return null;
        }

        $constructorDoc = $property->getDeclaringClass()->getConstructor()?->getDocComment();
        if (null === $constructorDoc || false === $constructorDoc) {
            return null;
        }

        foreach ($this->parse($constructorDoc)->getParamTagValues() as $tag) {
            if ('$' . $property->getName() === $tag->parameterName) {
                return self::nonEmpty((string)$tag->type);
            }
        }

        return null;
    }

    private static function nonEmpty(?string $type): ?string
    {
        return null === $type || '' === $type ? null : $type;
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

        foreach ($this->importTags($content) as $tag) {
            $sourceAlias = $tag->importedAlias;
            $classRef = $tag->importedFrom->name;
            $localAlias = $tag->importedAs ?? $sourceAlias;
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
     * Resolves the names in a parsed type that only make sense where it was written: imported
     * aliases become the names they were imported as, and class constants — `self::STATUS_*` —
     * become the literal values of the constants they match, read from $ownerClass (the class
     * whose docblock holds the type) when they say `self`.
     *
     * @param array<string, ImportedType> $imports
     */
    public function resolveImportedNames(ParsedType $type, array $imports, ?string $ownerClass = null): ParsedType
    {
        if ($type instanceof ClassConstantType) {
            return $this->resolveClassConstant($type, $ownerClass);
        }

        if ($type instanceof NameRefType) {
            if (isset($imports[$type->name])) {
                return new NameRefType($imports[$type->name]->targetTypeName);
            }

            return $type;
        }

        if ($type instanceof NullableType) {
            return new NullableType(
                inner: $this->resolveImportedNames($type->inner, $imports, $ownerClass),
                optional: $type->optional,
            );
        }

        if ($type instanceof ListType) {
            return new ListType($this->resolveImportedNames($type->inner, $imports, $ownerClass));
        }

        if ($type instanceof MapType) {
            return new MapType(
                key: $this->resolveImportedNames($type->key, $imports, $ownerClass),
                value: $this->resolveImportedNames($type->value, $imports, $ownerClass),
            );
        }

        if ($type instanceof TupleType) {
            return new TupleType(array_map(
                fn (ParsedType $element): ParsedType => $this->resolveImportedNames($element, $imports, $ownerClass),
                $type->elements,
            ), unsealed: $type->unsealed);
        }

        if ($type instanceof ShapeType) {
            $fields = [];
            foreach ($type->fields as $field) {
                $fields[] = new ShapeField(
                    name: $field->name,
                    type: $this->resolveImportedNames($field->type, $imports, $ownerClass),
                    optional: $field->optional,
                );
            }

            return new ShapeType($fields, unsealed: $type->unsealed);
        }

        if ($type instanceof IntersectionType) {
            $base = $this->resolveImportedNames($type->base, $imports, $ownerClass);
            $extra = $this->resolveImportedNames($type->extra, $imports, $ownerClass);
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
                fn(ParsedType $member): ParsedType => $this->resolveImportedNames($member, $imports, $ownerClass),
                $type->types,
            ));
        }

        return $type;
    }

    /**
     * The literal values of the constants a `Class::PATTERN` names, as one literal or a union of
     * them. `value-of<…>` of an array constant takes its elements instead.
     */
    private function resolveClassConstant(ClassConstantType $type, ?string $ownerClass): ParsedType
    {
        $class = $this->constantClass($type->class, $ownerClass);
        $pattern = '/^' . str_replace('\\*', '.*', preg_quote($type->pattern, '/')) . '$/';

        $values = [];
        foreach ((new ReflectionClass($class))->getReflectionConstants() as $constant) {
            if (1 !== preg_match($pattern, $constant->getName())) {
                continue;
            }

            $value = $constant->getValue();
            foreach ($type->valueOf && \is_array($value) ? array_values($value) : [$value] as $literal) {
                if (!\is_string($literal) && !\is_int($literal) && !\is_float($literal) && !\is_bool($literal)) {
                    throw new RuntimeException(\sprintf(
                        '`%s::%s` matches %s::%s, whose value is not a string, number or bool, so it has no literal type.',
                        $type->class,
                        $type->pattern,
                        $class,
                        $constant->getName(),
                    ));
                }
                if (!\in_array($literal, $values, true)) {
                    $values[] = $literal;
                }
            }
        }

        if ([] === $values) {
            throw new RuntimeException(\sprintf('`%s::%s` matches no constant of %s.', $type->class, $type->pattern, $class));
        }

        $literals = array_map(static fn(string|int|float|bool $value): LiteralType => new LiteralType($value), $values);

        return 1 === \count($literals) ? $literals[0] : new UnionType($literals);
    }

    /**
     * @return class-string
     */
    private function constantClass(string $class, ?string $ownerClass): string
    {
        if ('self' === $class || 'static' === $class) {
            if (null === $ownerClass) {
                throw new RuntimeException(\sprintf('`%s::` needs the class that owns the type, and none was given.', $class));
            }

            $class = $ownerClass;
        }

        $candidates = [ltrim($class, '\\')];
        if (!str_starts_with($class, '\\') && null !== $ownerClass && null !== $namespace = $this->namespace($ownerClass)) {
            $candidates[] = $namespace . '\\' . $class;
        }

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) || interface_exists($candidate) || enum_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(\sprintf(
            'Class "%s" in a constant type could not be found. Write self::, or its fully qualified name.',
            $class,
        ));
    }

    /**
     * Every docblock in the input.
     *
     * Callers pass either a whole PHP file — the collectors and the scaffolder do — or a single
     * docblock, as the PHPStan rules do. A file is split with the tokeniser rather than by
     * pattern, so a `/**` inside a string literal or a plain comment cannot be mistaken for one.
     *
     * @return list<string>
     */
    private function docComments(string $content): array
    {
        if (str_starts_with(ltrim($content), '/**')) {
            return [$content];
        }

        $docComments = [];

        foreach (token_get_all($content) as $token) {
            if (\is_array($token) && \T_DOC_COMMENT === $token[0]) {
                $docComments[] = $token[1];
            }
        }

        return $docComments;
    }

    /**
     * @return list<TypeAliasImportTagValueNode>
     */
    private function importTags(string $content): array
    {
        $tags = [];

        foreach ($this->docComments($content) as $docComment) {
            foreach ($this->parse($docComment)->getTypeAliasImportTagValues() as $tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private function parse(string $docComment): PhpDocNode
    {
        return $this->parser->parse(new TokenIterator($this->lexer->tokenize($docComment)));
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
