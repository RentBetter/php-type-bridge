<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
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
    public function __construct(
        private EmittedNames $names,
        private SymbolRegistry $symbols,
    ) {}

    public function convert(ParsedType $type, ConversionScope $scope): string
    {
        if ($type instanceof ScalarType) {
            return match ($type->type) {
                'string' => 'string',
                'int', 'float', 'numeric' => 'number',
                'bool' => 'boolean',
                'mixed' => 'unknown',
                'null' => 'null',
                default => throw new RuntimeException(\sprintf('Unknown scalar type "%s".', $type->type)),
            };
        }

        if ($type instanceof NullableType) {
            if ($type->optional) {
                return $this->convert($type->inner, $scope);
            }

            return $this->convert($type->inner, $scope) . ' | null';
        }

        if ($type instanceof ListType) {
            return $this->convert($type->inner, $scope) . '[]';
        }

        if ($type instanceof ValueOfType) {
            return $this->enumName($type->enumClass);
        }

        if ($type instanceof NameRefType) {
            if (isset($scope->importedSymbols[$type->name])) {
                return $scope->importedSymbols[$type->name];
            }

            return $this->symbols->resolve($scope->domain, $type->name);
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

            return '{ ' . implode('; ', $fields) . ' }';
        }

        if ($type instanceof UnionType) {
            return implode(' | ', array_map(fn(ParsedType $member): string => $this->convert($member, $scope), $type->types));
        }

        if ($type instanceof IntersectionType) {
            return $this->convert($type->base, $scope) . ' & ' . $this->convert($type->extra, $scope);
        }

        throw new RuntimeException(\sprintf('Unhandled parsed type "%s".', $type::class));
    }

    public function enumName(string $enumClass): string
    {
        return $this->names->enumName($enumClass);
    }
}
