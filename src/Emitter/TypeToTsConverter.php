<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Config\IncludeConvention;
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
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
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
     * A shape key as a TypeScript property name: as written when it can be (`id`, `$type`), and
     * quoted when it cannot (`'my-key'`). The parser hands keys over unquoted, whatever the
     * PHPDoc spelling, so this is the only place that decides.
     */
    public static function propertyName(string $name): string
    {
        return 1 === preg_match(self::BARE_PROPERTY_NAME, $name) ? $name : self::stringLiteral($name);
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

        if ($type instanceof ValueOfType) {
            return $this->enumName($type->enumClass);
        }

        if ($type instanceof IdOfType) {
            if (null === $this->enumIds) {
                throw new RuntimeException(\sprintf(
                    '`id-of<%s>` needs an %s, which this converter was built without.',
                    $type->enumClass,
                    EnumIdSymbolResolver::class,
                ));
            }

            return $this->enumIds->resolve($type->enumClass)->canonicalName;
        }

        if ($type instanceof NameRefType) {
            if (isset($scope->importedSymbols[$type->name])) {
                return $scope->importedSymbols[$type->name];
            }

            // A name no alias answers to, written where it means a class: the JSON the class
            // serialises to. An alias of the same name always wins.
            if (null !== $type->class && null !== $this->classTypes && !$this->symbols->has($scope->domain, $type->name)) {
                $symbol = $this->classTypes->resolve($type->class);

                return $symbol->targetDomain === $scope->domain
                    ? $symbol->canonicalName
                    : $scope->foreignAliases[$symbol->targetDomain][$symbol->canonicalName] ?? $symbol->canonicalName;
            }

            return $this->symbols->resolve($scope->domain, $type->name);
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
                $optional = $field->optional || $this->isIncluded($field->type);
                $fieldType = $field->type;
                if ($fieldType instanceof NullableType && $fieldType->optional) {
                    $optional = true;
                    $fieldType = $fieldType->inner;
                }

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
        $converted = $this->convert($inner, $scope);
        $compound = $inner instanceof UnionType
            || $inner instanceof IntersectionType
            || ($inner instanceof NullableType && !$inner->optional)
            || ($inner instanceof ScalarType && \in_array($inner->base(), ['scalar', 'array-key'], true));

        return $compound ? '(' . $converted . ')[]' : $converted . '[]';
    }

    public function enumName(string $enumClass): string
    {
        return $this->names->enumName($enumClass);
    }
}
