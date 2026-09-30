<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Parser\ClassConstantType;
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

    public function __construct(
        private EmittedNames $names,
        private SymbolRegistry $symbols,
        private ?EnumIdSymbolResolver $enumIds = null,
        private ?ClassTypeResolver $classTypes = null,
    ) {}

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
                return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $type->value) . "'";
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
                $optional = $field->optional;
                $fieldType = $field->type;
                if ($fieldType instanceof NullableType && $fieldType->optional) {
                    $optional = true;
                    $fieldType = $fieldType->inner;
                }

                return \sprintf('%s%s: %s', $field->name, $optional ? '?' : '', $this->convert($fieldType, $scope));
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
