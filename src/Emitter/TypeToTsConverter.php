<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Config\IncludeConvention;
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
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ScopedType;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Support\TypeScriptTypes;
use RuntimeException;

/**
 * Converts a {@see ParsedType} into its TypeScript representation.
 *
 * This is the convention-agnostic "type language" shared by every emitter. It is
 * stateless: all per-declaration context (current domain, imported-symbol aliases)
 * arrives via {@see ConversionScope}, and cross-domain symbols resolve through the
 * shared {@see SymbolRegistry}.
 */
final readonly class TypeToTsConverter
{
    /**
     * The index signature an unsealed shape (`array{…, ...}`) emits: keys beyond the listed ones
     * may be there. `unknown`, whatever the extras were declared as, so the listed keys' own
     * types never conflict with it.
     */
    public const string UNSEALED_INDEX = '[key: string]: unknown';

    /**
     * A TypeScript property name TypeScript reads without quotes: an identifier — `$` counts, so
     * `$type` stays bare — or a non-negative integer.
     */
    private const string BARE_PROPERTY_NAME = '/^(?:[A-Za-z_$][A-Za-z0-9_$]*|0|[1-9][0-9]*)$/';

    /**
     * The type of the class a name carries, where it is the class that the name means: where no
     * alias of the module it is written in has that name. Null where the name is that module's alias.
     *
     * @param string $aliasDomain the module the name is written in ({@see ConversionScope::$aliasDomain})
     */
    public function classSymbol(NameRefType $type, string $aliasDomain): ?EmitImport
    {
        if (null === $type->class || null === $this->classTypes || $this->symbols->has($aliasDomain, $type->name)) {
            return null;
        }

        return $this->classTypes->resolve($type->class);
    }

    /**
     * What a name refers to in a scope — the one choice converting a type and collecting what its
     * module imports both make. A name used bare that the file imports is that import, under the name
     * it is imported as (no module: it is named already). Otherwise, written where it means a class,
     * the JSON the class serialises to — unless an alias of the same name in the module it is written
     * in wins: the module's, or for an inherited property its base's, whichever module emits it — or
     * that module's alias. A config alias is every module's, declared by each that uses it: this one's.
     * A name resolved to a class is never an import's, whatever its short name.
     *
     * @return array{0: string|null, 1: string} the module the symbol is declared in, null for an
     *         import's local name; and the symbol
     */
    public function referenceOf(NameRefType $type, ConversionScope $scope): array
    {
        if (null === $type->class && isset($scope->importedSymbols[$type->name])) {
            return [null, $scope->importedSymbols[$type->name]];
        }

        $aliasDomain = $this->symbols->isShared($type->name) ? $scope->domain : $scope->aliasDomain ?? $scope->domain;
        $symbol = $this->classSymbol($type, $aliasDomain);

        return null !== $symbol ? [$symbol->targetDomain, $symbol->canonicalName] : [$aliasDomain, $this->symbols->resolve($aliasDomain, $type->name)];
    }

    /**
     * The scope a type written in another file is read in ({@see ScopedType}): that file's imports,
     * under the names this module imports their types as, and its module's aliases for its bare names.
     */
    public function scopeOf(ScopedType $type, ConversionScope $scope): ConversionScope
    {
        $symbols = [];
        foreach (ImportedType::usedIn($type->imports, $type->inner) as $import) {
            $symbols[$import->localAlias] = self::localName($import->targetDomain, $this->symbols->resolve($import->targetDomain, $import->targetTypeName), $scope);
        }

        return new ConversionScope($scope->domain, $symbols, $scope->foreignAliases, null === $type->domain || $type->domain === $scope->domain ? null : $type->domain);
    }

    /**
     * A shape key as a TypeScript property name: as written when it can be (`id`, `$type`), and
     * quoted when it cannot (`'my-key'`). The parser hands keys over unquoted, whatever the
     * PHPDoc spelling, so this is the only place that decides.
     */
    public static function propertyName(string $name): string
    {
        return 1 === preg_match(self::BARE_PROPERTY_NAME, $name) ? $name : self::stringLiteral($name);
    }

    /**
     * What a symbol of a module is called where the scope is emitted: its own name in the same
     * module, else the name it is imported under.
     */
    private static function localName(string $domain, string $symbol, ConversionScope $scope): string
    {
        return $domain === $scope->domain ? $symbol : $scope->foreignAliases[$domain][$symbol] ?? $symbol;
    }

    public static function stringLiteral(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    /**
     * @param IncludeConvention $includes what marks a key that is only sent when asked for
     */
    public function __construct(
        private EmittedNames $names,
        private SymbolRegistry $symbols,
        private ?EnumIdSymbolResolver $enumIds = null,
        private ?ClassTypeResolver $classTypes = null,
        public IncludeConvention $includes = new IncludeConvention(),
    ) {}

    /**
     * Whether a key of this type is only sent when the request asks for it: it is wrapped in
     * one of the project's include generics (`included<T>`).
     */
    public function isIncluded(ParsedType $type): bool
    {
        return $type instanceof GenericType && $this->includes->isIncludeType($type->name);
    }

    public function convert(ParsedType $type, ConversionScope $scope): string
    {
        if ($type instanceof ScalarType) {
            return match ($type->base()) {
                'string' => 'string',
                'int', 'float', 'numeric' => 'number',
                'bool' => 'boolean',
                'mixed' => 'unknown',
                'null' => 'null',
                'scalar' => 'string | number | boolean',
                'array-key' => 'string | number',
                default => throw new RuntimeException(\sprintf('Unknown scalar type "%s".', $type->type)),
            };
        }

        if ($type instanceof LiteralType) {
            if (\is_string($type->value)) {
                return self::stringLiteral($type->value);
            }

            if (\is_bool($type->value)) {
                return $type->value ? 'true' : 'false';
            }

            return (string) $type->value;
        }

        if ($type instanceof NullableType) {
            if ($type->optional) {
                return $this->convert($type->inner, $scope);
            }

            return $this->convert($type->inner, $scope) . ' | null';
        }

        if ($type instanceof ListType) {
            return $this->listOf($type->inner, $scope);
        }

        if ($type instanceof MapType) {
            // An array-key-keyed array is a list when its keys happen to run 0..n and an object
            // otherwise, and json_encode writes it as whichever it is at the time.
            if ($type->key instanceof ScalarType && 'array-key' === $type->key->base()) {
                return \sprintf('Record<string, %s> | %s', $this->convert($type->value, $scope), $this->listOf($type->value, $scope));
            }

            return \sprintf(
                'Record<%s, %s>',
                $this->convert($type->key, $scope),
                $this->convert($type->value, $scope),
            );
        }

        // Under the name this module imports it as, where that clashes with one of its own.
        if ($type instanceof ValueOfType) {
            return self::localName($this->names->enumDomain($type->enumClass), $this->enumName($type->enumClass), $scope);
        }

        if ($type instanceof IdOfType) {
            if (null === $this->enumIds) {
                throw new RuntimeException(\sprintf(
                    '`id-of<%s>` needs an %s, which this converter was built without.',
                    $type->enumClass,
                    EnumIdSymbolResolver::class,
                ));
            }

            $ids = $this->enumIds->resolve($type->enumClass);

            return self::localName($ids->targetDomain, $ids->canonicalName, $scope);
        }

        // Written in another file: read with that file's imports, its bare names that module's.
        if ($type instanceof ScopedType) {
            return $this->convert($type->inner, $this->scopeOf($type, $scope));
        }

        if ($type instanceof NameRefType) {
            [$domain, $symbol] = $this->referenceOf($type, $scope);

            return null === $domain ? $symbol : self::localName($domain, $symbol, $scope);
        }

        if ($type instanceof TupleType) {
            $elements = array_map(
                fn (ParsedType $element): string => $this->convert($element, $scope),
                $type->elements,
            );
            // An unsealed tuple is a rest element in TypeScript: `[string, number, ...unknown[]]`.
            if ($type->unsealed) {
                $elements[] = '...unknown[]';
            }

            return '[' . implode(', ', $elements) . ']';
        }

        if ($type instanceof ShapeType) {
            $fields = array_map(function (ShapeField $field) use ($scope): string {
                $optional = $field->mayBeLeftOut() || $this->isIncluded($field->type);
                // `key: ?T` is emitted `key?: T`: left out, not sent as null.
                $fieldType = $field->type instanceof NullableType && $field->type->optional ? $field->type->inner : $field->type;

                return \sprintf('%s%s: %s', self::propertyName($field->name), $optional ? '?' : '', $this->convert($fieldType, $scope));
            }, $type->fields);
            if ($type->unsealed) {
                $fields[] = self::UNSEALED_INDEX;
            }

            return '{ ' . implode('; ', $fields) . ' }';
        }

        if ($type instanceof UnionType) {
            return implode(' | ', array_map(fn(ParsedType $member): string => $this->convert($member, $scope), $type->types));
        }

        if ($type instanceof IntersectionType) {
            return $this->convert($type->base, $scope) . ' & ' . $this->convert($type->extra, $scope);
        }

        // An include generic (`included<T>`) is its type; the key it sits on is what is optional.
        // A ref generic (`ref<T>`) is an id that expands to T: `Ref<T>`, which WithExpands reads.
        // An enum generic (`enum<T>`) is the enum T, sent as its case.
        if ($type instanceof GenericType) {
            $isRef = $this->includes->isRefType($type->name);
            if (!$isRef && !$this->includes->isIncludeType($type->name) && !$this->includes->isEnumType($type->name)) {
                throw new RuntimeException(\sprintf(
                    'Unknown generic `%s<…>`. If it marks a key that is only sent when a request asks for it, list it in the `includes.types` config; if it is a reference a request can expand, in `includes.refTypes`; if it wraps an enum, in `includes.enumTypes`.',
                    $type->name,
                ));
            }
            if (1 !== \count($type->arguments)) {
                throw new RuntimeException(\sprintf('`%s<…>` wraps one type; it was given %d.', $type->name, \count($type->arguments)));
            }

            $inner = $this->convert($type->arguments[0], $scope);

            return $isRef ? \sprintf('%s<%s>', TypeScriptEmitter::REF_TYPE, $inner) : $inner;
        }

        if ($type instanceof ClassConstantType) {
            throw new RuntimeException(\sprintf(
                '`%s::%s` reached the emitter unresolved. Class constants are resolved against the class that owns the shape; pass it to PhpDocTypeHelper::resolveImportedNames().',
                $type->class,
                $type->pattern,
            ));
        }

        throw new RuntimeException(\sprintf('Unhandled parsed type "%s".', $type::class));
    }

    /**
     * `T[]`, parenthesised where T is a union or intersection — `A | B[]` is a union with a
     * list in it, not a list of either.
     */
    private function listOf(ParsedType $inner, ConversionScope $scope): string
    {
        // Read off what it converts to: a union a scalar, a map or another file's type makes too.
        $converted = $this->convert($inner, $scope);

        return TypeScriptTypes::needsBrackets($converted) ? '(' . $converted . ')[]' : $converted . '[]';
    }

    public function enumName(string $enumClass): string
    {
        return $this->names->enumName($enumClass);
    }
}
