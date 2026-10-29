<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use Closure;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use PTGS\TypeBridge\Attribute\ValueOfName;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\ClassConstantType;
use PTGS\TypeBridge\Parser\GenericType;
use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ParsedTypes;
use PTGS\TypeBridge\Parser\ScopedType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
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
     * A file's `@phpstan-import-type` tags, by the alias each is known by locally, every one of which
     * must be found in the source tree: the first that isn't fails.
     *
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
        $read = $this->importedTypes($this->importTags($content), $ownerClass, $srcDir, $classFiles, $shortNameMap, $domainGuesser, $this->classImports($content));
        foreach ($read['unresolved'] as $why) {
            throw new RuntimeException($why);
        }

        return $read['imports'];
    }

    /**
     * The types a file's `@phpstan-import-type` tags (read with {@see self::importTags()}) import, by
     * the alias each is known by locally. An import whose target is not in the source tree is set
     * aside, with why, rather than thrown: a file is read for some of its types only — a response's
     * base class, whose protected properties may name anything — and such an import matters only if
     * what is read uses it ({@see self::unresolvedImportUsedIn()}).
     *
     * @param list<TypeAliasImportTagValueNode> $tags
     * @param array<string, string> $classFiles
     * @param array<string, list<string>> $shortNameMap
     * @param array<string, string> $classImports the file's `use` statements ({@see self::classImports()}),
     *        which name the class an import is from as they name any
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>} `unresolved` is local alias => why
     */
    public function importedTypes(
        array $tags,
        string $ownerClass,
        string $srcDir,
        array $classFiles,
        array $shortNameMap,
        DomainGuesser $domainGuesser,
        array $classImports = [],
    ): array {
        $imports = [];
        $unresolved = [];
        foreach ($tags as $tag) {
            try {
                $import = $this->importedType($tag, $ownerClass, $srcDir, $classFiles, $shortNameMap, $domainGuesser, $classImports);
                $imports[$import->localAlias] = $import;
            } catch (RuntimeException $e) {
                $unresolved[$tag->importedAs ?? $tag->importedAlias] = $e->getMessage();
            }
        }

        return ['imports' => $imports, 'unresolved' => $unresolved];
    }

    /**
     * An import whose target was set aside as unresolved by
     * {@see self::importedTypes()}, if the type uses it — names it as a type, which
     * a shape key or a string literal spelt the same does not: the error it would have thrown, with
     * where it is used — so a file is failed only over an import something read from it needs.
     *
     * @param ParsedType $type as parsed, before its names are resolved
     * @param array<string, string> $unresolved local alias => why
     */
    public function unresolvedImportUsedIn(ParsedType $type, array $unresolved, string $where): ?RuntimeException
    {
        $used = array_key_first(array_intersect_key($unresolved, ParsedTypes::names($type)));

        return null === $used ? null : new RuntimeException(\sprintf('%s %s is typed with it.', $unresolved[$used], $where));
    }

    /**
     * @param array<string, string> $classFiles
     * @param array<string, list<string>> $shortNameMap
     * @param array<string, string> $classImports
     */
    private function importedType(
        TypeAliasImportTagValueNode $tag,
        string $ownerClass,
        string $srcDir,
        array $classFiles,
        array $shortNameMap,
        DomainGuesser $domainGuesser,
        array $classImports,
    ): ImportedType {
        $sourceAlias = $tag->importedAlias;
        $targetClass = $this->resolveClassReference($tag->importedFrom->name, $ownerClass, $classFiles, $shortNameMap, $classImports);
        $targetFile = $classFiles[$targetClass] ?? null;
        if (null === $targetFile) {
            throw new RuntimeException(\sprintf('Imported type target "%s" for "%s" was not found.', $targetClass, $ownerClass));
        }

        return new ImportedType(
            localAlias: $tag->importedAs ?? $sourceAlias,
            targetClass: $targetClass,
            targetTypeName: $this->emittedTypeName($sourceAlias, $targetClass),
            targetDomain: $domainGuesser->guess($srcDir, $targetFile),
            sourceAlias: $sourceAlias,
        );
    }

    /**
     * Resolves the names in a parsed type that only make sense where it was written: an imported
     * alias keeps the name the file imports it as — the scope the type is converted in
     * ({@see \PTGS\TypeBridge\Emitter\EmitContext::scopeFor()}) says which type that is, so one
     * imported under another name is never taken for an alias the file declares with its emitted
     * name; a bare name that is not an alias of the file but names a class is annotated with that
     * class, so the emitter can fall back to the class's own type; and class constants —
     * `self::STATUS_*` — become the literal values of the constants they match.
     *
     * @param array<string, ImportedType> $imports
     * @param ?string $ownerClass the class whose docblock holds the type
     * @param array<string, string> $classImports the file's `use` statements, alias => class ({@see self::classImports()})
     * @param list<string> $localAliases the aliases the file declares itself
     * @param ?string $selfClass the class `self::` means, where it isn't `$ownerClass`: in a
     *        trait, the class using it — as PHP and PHPStan read it
     */
    public function resolveImportedNames(
        ParsedType $type,
        array $imports,
        ?string $ownerClass = null,
        array $classImports = [],
        array $localAliases = [],
        ?string $selfClass = null,
    ): ParsedType {
        return $this->mapLeaves($type, function (ParsedType $leaf) use ($imports, $ownerClass, $classImports, $localAliases, $selfClass): ParsedType {
            if ($leaf instanceof ClassConstantType) {
                return $this->resolveClassConstant($leaf, $ownerClass, $classImports, $selfClass ?? $ownerClass);
            }

            if (!$leaf instanceof NameRefType) {
                return $leaf;
            }

            if (null === $leaf->class && isset($imports[$leaf->name])) {
                return $leaf;
            }

            // Written as a qualified name: a class by syntax, resolved as PHP would.
            if (null !== $leaf->class) {
                return new NameRefType($leaf->name, $this->resolveClassName($leaf->class, $ownerClass, $classImports) ?? ltrim($leaf->class, '\\'));
            }

            if (\in_array($leaf->name, $localAliases, true)) {
                return $leaf;
            }

            $class = $this->resolveClassName($leaf->name, $ownerClass, $classImports);

            return null === $class ? $leaf : new NameRefType($leaf->name, $class);
        });
    }

    /**
     * A type written outside the source tree, its names as that file means them: each the class it
     * resolved to ({@see self::resolveImportedNames()}), spelt in full, so no module's alias of the
     * same short name answers to it. Such a file is in no module, so a name that resolved to no
     * class fails, with why — but for a project-wide alias, which PHPStan reads in any file.
     *
     * @param list<string> $sharedNames the project-wide aliases (`typeAliases`)
     */
    public function namesAsClasses(ParsedType $type, string $why, array $sharedNames = []): ParsedType
    {
        return $this->mapLeaves($type, static function (ParsedType $leaf) use ($why, $sharedNames): ParsedType {
            if (!$leaf instanceof NameRefType || (null === $leaf->class && \in_array($leaf->name, $sharedNames, true))) {
                return $leaf;
            }
            if (null === $leaf->class) {
                throw new RuntimeException(\sprintf('`%s` names no class where it is written. %s', $leaf->name, $why));
            }

            return new NameRefType($leaf->class, $leaf->class);
        });
    }

    /**
     * A type written outside the source tree, its enums as that file names them: `value-of<Status>`
     * is the Status its `use` statements and namespace resolve, spelt in full, not whichever enum of
     * the source tree has that short name. One outside the source tree is never emitted, so a type
     * naming it fails, with why.
     *
     * @param array<string, string> $classImports the file's `use` statements ({@see self::classImports()})
     * @param array<string, string> $classFiles   the source tree's classes
     */
    public function enumsAsWritten(ParsedType $type, string $ownerClass, array $classImports, array $classFiles, string $why): ParsedType
    {
        return $this->mapLeaves($type, function (ParsedType $leaf) use ($ownerClass, $classImports, $classFiles, $why): ParsedType {
            if (!$leaf instanceof ValueOfType && !$leaf instanceof IdOfType) {
                return $leaf;
            }
            $enum = $this->resolveClassName($leaf->enumClass, $ownerClass, $classImports) ?? ltrim($leaf->enumClass, '\\');
            if (!isset($classFiles[$enum])) {
                throw new RuntimeException(\sprintf('`%s` names %s, outside the source tree: an enum TypeBridge never emits. %s', $leaf->enumClass, $enum, $why));
            }

            return $leaf instanceof ValueOfType ? new ValueOfType($enum) : new IdOfType($enum);
        });
    }

    /**
     * The file's `use` statements for classes, as alias => fully qualified name. Only those
     * before the first class-like declaration: a `use` inside one imports a trait.
     *
     * @return array<string, string>
     */
    public function classImports(string $content): array
    {
        return $this->classImportsIn(\PhpToken::tokenize($content));
    }

    /**
     * What a file says, read out of it in one pass: the aliases it declares
     * ({@see self::extractPhpStanTypes()}), the classes its `use` statements name
     * ({@see self::classImports()}) and its import tags ({@see self::importTags()}) — the file
     * tokenised once, each docblock parsed once.
     *
     * And the types it binds a trait's template params to where a class or trait in it uses the
     * trait — `@use Trait<…>` on the `use` statement (or the class), ranked as PHPStan ranks them:
     * `@phpstan-use`, `@template-use`, `@use` — by the trait's short name, as it is written.
     *
     * @return array{definitions: array<string, string>, classImports: array<string, string>, importTags: list<TypeAliasImportTagValueNode>, uses: array<string, list<string>>}
     */
    public function readFile(string $content): array
    {
        $tokens = \PhpToken::tokenize($content);
        $definitions = [];
        $importTags = [];
        $uses = [];
        foreach ($tokens as $token) {
            if (!$token->is(\T_DOC_COMMENT)) {
                continue;
            }
            $node = $this->parse($token->text);
            foreach ($node->getTypeAliasTagValues() as $tag) {
                $definitions[$tag->alias] = (string) $tag->type;
            }
            foreach ($node->getTypeAliasImportTagValues() as $tag) {
                $importTags[] = $tag;
            }
            foreach (['@phpstan-use', '@template-use', '@use'] as $rank => $tagName) {
                foreach ($node->getUsesTagValues($tagName) as $use) {
                    $uses[ClassNames::short(ltrim($use->type->type->name, '\\'))][$rank] ??= array_values(array_map(static fn (TypeNode $arg): string => (string) $arg, $use->type->genericTypes));
                }
            }
        }

        // Of a trait's bindings, the best-ranked.
        $bound = [];
        foreach ($uses as $trait => $ranked) {
            ksort($ranked);
            $bound[$trait] = reset($ranked);
        }

        return [
            'definitions' => $definitions,
            'classImports' => $this->classImportsIn($tokens),
            'importTags' => $importTags,
            'uses' => $bound,
        ];
    }

    /**
     * @param array<\PhpToken> $tokens
     *
     * @return array<string, string>
     */
    private function classImportsIn(array $tokens): array
    {
        $imports = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM])) {
                break;
            }
            if (!$token->is(\T_USE)) {
                continue;
            }

            // Read to the semicolon: `use A\B;`, `use A\B as C;`, `use A\{B, C as D};`. Function
            // and constant imports are skipped.
            $statement = '';
            for ($i++; $i < $count && ';' !== $tokens[$i]->text; $i++) {
                $statement .= $tokens[$i]->text;
            }
            $statement = trim($statement);
            if (str_starts_with($statement, 'function ') || str_starts_with($statement, 'const ')) {
                continue;
            }

            // Several in one statement, by commas: `use A\B, C\D as E;`.
            $prefix = '';
            $names = explode(',', $statement);
            if (1 === preg_match('/^(.*?)\\\\?\{(.*)\}$/s', $statement, $group)) {
                $prefix = trim($group[1], '\\ ') . '\\';
                $names = explode(',', $group[2]);
            }

            foreach ($names as $name) {
                $parts = preg_split('/\s+as\s+/i', trim($name));
                if (false === $parts || '' === $parts[0]) {
                    continue;
                }
                $class = ltrim($prefix . trim($parts[0]), '\\');
                $alias = isset($parts[1]) ? trim($parts[1]) : ClassNames::short($class);
                $imports[$alias] = $class;
            }
        }

        return $imports;
    }

    /**
     * Rebuilds a parsed type with $leaf applied to every name-like leaf, leaving its structure
     * as it was.
     *
     * @param Closure(ParsedType): ParsedType $leaf
     */
    private function mapLeaves(ParsedType $type, Closure $leaf): ParsedType
    {
        if ($type instanceof NullableType) {
            return new NullableType(inner: $this->mapLeaves($type->inner, $leaf), optional: $type->optional);
        }

        if ($type instanceof ListType) {
            return new ListType($this->mapLeaves($type->inner, $leaf));
        }

        if ($type instanceof MapType) {
            return new MapType(key: $this->mapLeaves($type->key, $leaf), value: $this->mapLeaves($type->value, $leaf));
        }

        if ($type instanceof TupleType) {
            return new TupleType(array_map(
                fn(ParsedType $element): ParsedType => $this->mapLeaves($element, $leaf),
                $type->elements,
            ), unsealed: $type->unsealed);
        }

        if ($type instanceof ShapeType) {
            $fields = [];
            foreach ($type->fields as $field) {
                $fields[] = new ShapeField(
                    name: $field->name,
                    type: $this->mapLeaves($field->type, $leaf),
                    optional: $field->optional,
                );
            }

            return new ShapeType($fields, unsealed: $type->unsealed);
        }

        if ($type instanceof IntersectionType) {
            $base = $this->mapLeaves($type->base, $leaf);
            $extra = $this->mapLeaves($type->extra, $leaf);
            if (!$base instanceof NameRefType || !$extra instanceof ShapeType) {
                throw new RuntimeException('Resolved intersection type became invalid after import resolution.');
            }

            return new IntersectionType(base: $base, extra: $extra);
        }

        if ($type instanceof UnionType) {
            return new UnionType(array_map(
                fn(ParsedType $member): ParsedType => $this->mapLeaves($member, $leaf),
                $type->types,
            ));
        }

        if ($type instanceof GenericType) {
            return new GenericType($type->name, array_map(
                fn(ParsedType $argument): ParsedType => $this->mapLeaves($argument, $leaf),
                $type->arguments,
            ));
        }

        return $leaf($type);
    }

    /**
     * The literal values of the constants a `Class::PATTERN` names, as one literal or a union of
     * them. `value-of<…>` of an array constant takes its elements instead.
     *
     * @param array<string, string> $classImports
     */
    private function resolveClassConstant(ClassConstantType $type, ?string $ownerClass, array $classImports, ?string $selfClass): ParsedType
    {
        $class = 'self' === $type->class || 'static' === $type->class
            ? ($selfClass ?? throw new RuntimeException(\sprintf('`%s::` needs the class that owns the type, and none was given.', $type->class)))
            : $this->resolveClassName($type->class, $ownerClass, $classImports);
        if (null === $class || !PhpFileClassLocator::loads($class)) {
            throw new RuntimeException(\sprintf(
                'Class "%s" in `%s::%s` could not be found. Write self::, import the class, or give its fully qualified name.',
                $type->class,
                $type->class,
                $type->pattern,
            ));
        }
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
     * The class a name means where it was written, as PHP resolves it: fully qualified, through
     * the file's `use` statements, in the owner's namespace, or global — or null when it is no
     * class at all (an alias).
     *
     * @param array<string, string> $classImports
     * @return ?class-string
     */
    public function resolveClassName(string $name, ?string $ownerClass, array $classImports): ?string
    {
        $candidates = [];
        if (str_starts_with($name, '\\')) {
            $candidates[] = ltrim($name, '\\');
        } else {
            $first = explode('\\', $name, 2);
            if (isset($classImports[$first[0]])) {
                $candidates[] = $classImports[$first[0]] . (isset($first[1]) ? '\\' . $first[1] : '');
            }
            if (null !== $ownerClass && null !== $namespace = ClassNames::namespaceOf($ownerClass)) {
                $candidates[] = $namespace . '\\' . $name;
            }
            $candidates[] = $name;
        }

        foreach ($candidates as $candidate) {
            // One whose declaration throws in this install — a require-dev parent — is no class here.
            if (PhpFileClassLocator::loads($candidate)) {
                return $candidate;
            }
        }

        return null;
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
     * A file's `@phpstan-import-type` (and `@psalm-import-type`) tags, as written.
     *
     * @return list<TypeAliasImportTagValueNode>
     */
    public function importTags(string $content): array
    {
        $tags = [];

        foreach ($this->docComments($content) as $docComment) {
            foreach ($this->parse($docComment)->getTypeAliasImportTagValues() as $tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * The template params a class's docblock declares, in the order first written, as PHPStan reads
     * them: each name by its highest-ranked spelling — `@phpstan-` over `@psalm-` over `@phan-` over
     * plain, `-covariant` and `-contravariant` alike — with its bound (`of …`) and default (`= …`) as
     * written.
     *
     * @return list<array{name: string, bound: string|null, default: string|null}>
     */
    public function templateParams(string $docComment): array
    {
        if ('' === $docComment) {
            return [];
        }

        $ranks = ['' => 0, 'phan' => 1, 'psalm' => 2, 'phpstan' => 3];
        $chosen = [];
        foreach ($this->parse($docComment)->getTags() as $tag) {
            if (!$tag->value instanceof TemplateTagValueNode || 1 !== preg_match('/^@(?:(phan|psalm|phpstan)-)?template(?:-covariant|-contravariant)?$/', $tag->name, $spelling)) {
                continue;
            }
            $rank = $ranks[$spelling[1] ?? ''];
            if (!isset($chosen[$tag->value->name]) || $rank > $chosen[$tag->value->name]['rank']) {
                $chosen[$tag->value->name] = ['rank' => $rank, 'value' => $tag->value];
            }
        }

        $params = [];
        foreach ($chosen as $name => ['value' => $value]) {
            $params[] = [
                'name' => $name,
                'bound' => null === $value->bound ? null : (string) $value->bound,
                'default' => null === $value->default ? null : (string) $value->default,
            ];
        }

        return $params;
    }

    /**
     * The types a class's docblock binds its parent's template params to, as written, PHPStan's own
     * spelling first — `@phpstan-extends`, then `@template-extends`, `@phan-inherits`,
     * `@phan-extends`, `@extends` — or null where it binds none.
     *
     * @return list<string>|null
     */
    public function extendsArgs(string $docComment): ?array
    {
        if ('' === $docComment) {
            return null;
        }

        $node = $this->parse($docComment);
        foreach (['@phpstan-extends', '@template-extends', '@phan-inherits', '@phan-extends', '@extends'] as $tagName) {
            foreach ($node->getExtendsTagValues($tagName) as $extends) {
                return array_values(array_map(static fn (TypeNode $arg): string => (string) $arg, $extends->type->genericTypes));
            }
        }

        return null;
    }

    /**
     * The type with each name it uses read in the scope of the file that writes it ({@see ScopedType}),
     * its shape — a `?T`, a list, a union, an `included<…>` — as it is. An intersection, whose base
     * is a name by syntax, is scoped whole — inside the `?` and beside the null of a type that has
     * them, which a reader asks of the type itself.
     *
     * @param list<ImportedType> $imports the writing file's
     */
    public function scoped(ParsedType $type, array $imports, ?string $domain): ParsedType
    {
        if ($type instanceof NullableType) {
            return new NullableType($this->scoped($type->inner, $imports, $domain), $type->optional);
        }
        if ($type instanceof UnionType) {
            return new UnionType(array_map(fn (ParsedType $member): ParsedType => $this->scoped($member, $imports, $domain), $type->types));
        }

        $intersects = false;
        for ($types = [$type]; [] !== $types && !$intersects;) {
            $part = array_pop($types);
            $intersects = $part instanceof IntersectionType;
            array_push($types, ...ParsedTypes::children($part));
        }
        if ($intersects) {
            return new ScopedType($type, $imports, $domain);
        }

        return $this->mapLeaves($type, static fn (ParsedType $leaf): ParsedType => $leaf instanceof NameRefType ? new ScopedType($leaf, $imports, $domain) : $leaf);
    }

    /**
     * The type with each name used bare that `$by` has replaced by what it maps it to: a template
     * param by the type it is bound to, an alias by the name it is read under elsewhere.
     *
     * @param array<string, ParsedType> $by
     */
    public function substituted(ParsedType $type, array $by): ParsedType
    {
        if ([] === $by) {
            return $type;
        }

        return $this->mapLeaves($type, static fn (ParsedType $leaf): ParsedType => $leaf instanceof NameRefType && null === $leaf->class && isset($by[$leaf->name]) ? $by[$leaf->name] : $leaf);
    }

    private function parse(string $docComment): PhpDocNode
    {
        return $this->parser->parse(new TokenIterator($this->lexer->tokenize($docComment)));
    }

    /**
     * @param array<string, string> $classFiles
     * @param array<string, list<string>> $shortNameMap
     * @param array<string, string> $classImports
     */
    private function resolveClassReference(
        string $classRef,
        string $ownerClass,
        array $classFiles,
        array $shortNameMap,
        array $classImports = [],
    ): string {
        $trimmed = ltrim($classRef, '\\');
        // As PHP and PHPStan resolve it: a name the file's `use` statements give is that class, ahead
        // of one of the same short name in its namespace.
        $first = explode('\\', $trimmed, 2);
        if (!str_starts_with($classRef, '\\') && isset($classImports[$first[0]])) {
            return $classImports[$first[0]] . (isset($first[1]) ? '\\' . $first[1] : '');
        }
        if (isset($classFiles[$trimmed])) {
            return $trimmed;
        }

        $namespace = ClassNames::namespaceOf($ownerClass);
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

    /**
     * The name a type alias is collected under — the one an import of it, an endpoint's input
     * reference and its own declaration all key it by: a named alias is its own name; a class's `_self`
     * is the class, and an enum's as {@see ValueOfName::shapeName()} says, `{Enum}Data` beside its value
     * union. It is declared as {@see \PTGS\TypeBridge\Emitter\EmittedNames} names it, the project's
     * suffix and prefix applied.
     */
    public function emittedTypeName(string $alias, string $ownerClass): string
    {
        if ('_self' !== $alias) {
            return $alias;
        }

        // An import's target may be one this install can't load: no enum, and set aside where it is read.
        return self::selfTypeName(ClassNames::short($ownerClass), PhpFileClassLocator::loadsEnum($ownerClass), null !== ValueOfName::of($ownerClass));
    }

    /**
     * The name a `_self` is collected under, by what declares it: a class's is the class; an enum's is
     * `{Enum}Data`, beside the value union under the enum's own name, or the enum's own name where it
     * names that union otherwise ({@see ValueOfName}). For a reader of the source that loads no class.
     */
    public static function selfTypeName(string $shortName, bool $enum, bool $valuesNamedOtherwise): string
    {
        return $enum && !$valuesNamedOtherwise ? $shortName . 'Data' : $shortName;
    }
}
