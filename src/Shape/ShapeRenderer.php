<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Shape;

use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
use RuntimeException;

/**
 * Renders a {@see ParsedType} back to the PHPDoc source PhpDocShapeParser accepts.
 *
 * The inverse of the parser, and deliberately its mirror: anything rendered here must parse back
 * to an equivalent tree, which is what makes generated shapes safe to write into source files.
 * A round-trip test pins that.
 */
final class ShapeRenderer
{
    private const string INDENT = ' *     ';

    /**
     * A complete `@phpstan-type _self = array{...}` block, including the comment markers, indented
     * to sit directly above a class declaration.
     */
    public function renderSelfDocBlock(ShapeType $shape): string
    {
        $lines = ['/**', ' * @phpstan-type _self = array{'];
        foreach ($shape->fields as $field) {
            $lines[] = self::INDENT . $field->name . ($field->optional ? '?' : '') . ': ' . $this->render($field->type) . ',';
        }
        $lines[] = ' * }';
        $lines[] = ' */';

        return implode("\n", $lines);
    }

    public function render(ParsedType $type): string
    {
        return match (true) {
            $type instanceof ScalarType => $type->type,
            $type instanceof NameRefType => $type->name,
            $type instanceof ValueOfType => 'value-of<' . $type->enumClass . '>',
            $type instanceof IdOfType => 'id-of<' . $type->enumClass . '>',
            $type instanceof ListType => 'list<' . $this->render($type->inner) . '>',
            $type instanceof MapType => 'array<' . $this->render($type->key) . ', ' . $this->render($type->value) . '>',
            $type instanceof LiteralType => $this->renderLiteral($type),
            $type instanceof NullableType => $this->renderNullable($type),
            $type instanceof UnionType => $this->renderUnion($type),
            $type instanceof IntersectionType => $this->render($type->base) . ' & ' . $this->render($type->extra),
            $type instanceof ShapeType => $this->renderInlineShape($type),
            default => throw new RuntimeException(\sprintf('Cannot render type "%s".', $type::class)),
        };
    }

    private function renderNullable(NullableType $type): string
    {
        // `?T` is the TS-optional form and `T|null` the meaningful-null one; the distinction is
        // the whole point of the flag, so it must survive rendering.
        return $type->optional
            ? '?' . $this->render($type->inner)
            : $this->render($type->inner) . '|null';
    }

    private function renderUnion(UnionType $type): string
    {
        return implode('|', array_map($this->render(...), $type->types));
    }

    private function renderInlineShape(ShapeType $type): string
    {
        $fields = [];
        foreach ($type->fields as $field) {
            $fields[] = $field->name . ($field->optional ? '?' : '') . ': ' . $this->render($field->type);
        }

        return 'array{' . implode(', ', $fields) . '}';
    }

    private function renderLiteral(LiteralType $type): string
    {
        return match (true) {
            \is_string($type->value) => "'" . $type->value . "'",
            \is_bool($type->value) => $type->value ? 'true' : 'false',
            default => (string) $type->value,
        };
    }
}
