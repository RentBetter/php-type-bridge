<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

use RuntimeException;

/**
 * Recursive descent parser for PHPStan array{} shape subset.
 *
 * Grammar:
 *   TypeDef    = Shape | NameRef '&' Shape | NameRef
 *   Shape      = 'array{' Fields '}'
 *   Fields     = Field (',' Field)* ','?
 *   Field      = Ident ':' Type | '?' Ident ':' Type
 *   Type       = '?' Type | 'value-of<' ClassName '>' | ScalarType | Shape | 'list<' Type '>' | NameRef | Type '|' 'null'
 *   ScalarType = 'string' | 'int' | 'float' | 'bool' | 'mixed' | 'numeric'
 */
final class PhpDocShapeParser
{
    private string $input;
    private int $pos;
    private int $len;

    public function parse(string $input): ParsedType
    {
        $this->input = $input;
        $this->pos = 0;
        $this->len = \strlen($input);

        $result = $this->parseTypeDef();
        $this->skipWhitespace();

        if ($this->pos < $this->len) {
            throw new RuntimeException(\sprintf(
                'Unexpected character at position %d: "%s" in "%s"',
                $this->pos,
                $this->input[$this->pos],
                $this->input,
            ));
        }

        return $result;
    }

    private function parseTypeDef(): ParsedType
    {
        $this->skipWhitespace();

        if ($this->lookAhead('array{')) {
            return $this->parseShape();
        }

        // Could be NameRef, NameRef & Shape, or a simple type
        $type = $this->parseType();

        $this->skipWhitespace();

        // Check for intersection: NameRef & array{...}
        if ($this->pos < $this->len && '&' === $this->input[$this->pos]) {
            if (!$type instanceof NameRefType) {
                throw new RuntimeException('Intersection left-hand side must be a type reference');
            }
            $this->pos++;
            $this->skipWhitespace();
            $right = $this->parseShape();

            return new IntersectionType($type, $right);
        }

        return $type;
    }

    private function parseShape(): ShapeType
    {
        $this->expect('array{');
        $fields = [];

        $this->skipWhitespace();
        while ($this->pos < $this->len && '}' !== $this->input[$this->pos]) {
            $fields[] = $this->parseField();
            $this->skipWhitespace();
            if ($this->pos < $this->len && ',' === $this->input[$this->pos]) {
                $this->pos++;
                $this->skipWhitespace();
            }
        }

        $this->expect('}');

        return new ShapeType($fields);
    }

    private function parseField(): ShapeField
    {
        $this->skipWhitespace();

        // Optional key: ?fieldName: type
        $optional = false;
        $fieldName = null;
        if ($this->pos < $this->len && '?' === $this->input[$this->pos]) {
            // Disambiguate: ?fieldName: vs ?type
            // Look ahead for ident followed by ':'
            $saved = $this->pos;
            $this->pos++;
            $this->skipWhitespace();
            $ident = $this->tryParseIdent();
            $this->skipWhitespace();
            if (null !== $ident && $this->pos < $this->len && ':' === $this->input[$this->pos]) {
                $optional = true;
                $fieldName = $ident;
            } else {
                // Not an optional field, restore
                $this->pos = $saved;
            }
        }

        if (!$optional) {
            $fieldName = $this->parseIdent();
        }

        $this->skipWhitespace();
        $this->expect(':');
        $this->skipWhitespace();

        $type = $this->parseType();

        return new ShapeField($fieldName, $type, $optional);
    }

    private function parseType(): ParsedType
    {
        $this->skipWhitespace();

        $type = $this->parseSingleType();

        // Check for union: type|null or type|type
        $this->skipWhitespace();
        if ($this->pos < $this->len && '|' === $this->input[$this->pos]) {
            $types = [$type];
            while ($this->pos < $this->len && '|' === $this->input[$this->pos]) {
                $this->pos++;
                $this->skipWhitespace();
                $types[] = $this->parseSingleType();
                $this->skipWhitespace();
            }

            // Special case: T|null → NullableType with optional=false (explicit null)
            $nonNullTypes = [];
            $hasNull = false;
            foreach ($types as $t) {
                if ($t instanceof ScalarType && 'null' === $t->type) {
                    $hasNull = true;
                } else {
                    $nonNullTypes[] = $t;
                }
            }

            if ($hasNull && 1 === \count($nonNullTypes)) {
                return new NullableType($nonNullTypes[0], optional: false);
            }

            if (\count($types) > 1) {
                return new UnionType($types);
            }
        }

        return $type;
    }

    private function parseSingleType(): ParsedType
    {
        $this->skipWhitespace();

        // ?type → NullableType (optional)
        if ($this->pos < $this->len && '?' === $this->input[$this->pos]) {
            $this->pos++;
            $inner = $this->parseSingleType();

            return new NullableType($inner, optional: true);
        }

        // array{...} → ShapeType
        if ($this->lookAhead('array{')) {
            return $this->parseShape();
        }

        // list<T>
        if ($this->lookAhead('list<')) {
            $this->expect('list<');
            $inner = $this->parseType();
            $this->skipWhitespace();
            $this->expect('>');

            return new ListType($inner);
        }

        // value-of<ClassName>
        if ($this->lookAhead('value-of<')) {
            $this->expect('value-of<');
            $className = $this->parseClassName();
            $this->skipWhitespace();
            $this->expect('>');

            return new ValueOfType($className);
        }

        // Scalar types
        foreach (['string', 'int', 'float', 'bool', 'mixed', 'numeric', 'null'] as $scalar) {
            if ($this->lookAhead($scalar) && !$this->isIdentChar($this->pos + \strlen($scalar))) {
                $this->pos += \strlen($scalar);

                return new ScalarType($scalar);
            }
        }

        // Name reference (IProjectBase, etc.)
        $name = $this->tryParseIdent();
        if (null !== $name) {
            return new NameRefType($name);
        }

        throw new RuntimeException(\sprintf(
            'Unexpected token at position %d in "%s"',
            $this->pos,
            $this->input,
        ));
    }

    private function parseIdent(): string
    {
        $ident = $this->tryParseIdent();
        if (null === $ident) {
            throw new RuntimeException(\sprintf(
                'Expected identifier at position %d in "%s"',
                $this->pos,
                $this->input,
            ));
        }

        return $ident;
    }

    private function tryParseIdent(): ?string
    {
        $start = $this->pos;
        while ($this->pos < $this->len && $this->isIdentChar($this->pos)) {
            $this->pos++;
        }

        if ($this->pos === $start) {
            return null;
        }

        return \substr($this->input, $start, $this->pos - $start);
    }

    private function parseClassName(): string
    {
        $start = $this->pos;
        while ($this->pos < $this->len && ($this->isIdentChar($this->pos) || '\\' === $this->input[$this->pos])) {
            $this->pos++;
        }

        if ($this->pos === $start) {
            throw new RuntimeException(\sprintf(
                'Expected class name at position %d in "%s"',
                $this->pos,
                $this->input,
            ));
        }

        return \substr($this->input, $start, $this->pos - $start);
    }

    private function isIdentChar(int $pos): bool
    {
        if ($pos >= $this->len) {
            return false;
        }

        $ch = $this->input[$pos];

        return ctype_alnum($ch) || '_' === $ch;
    }

    private function lookAhead(string $str): bool
    {
        return \substr($this->input, $this->pos, \strlen($str)) === $str;
    }

    private function expect(string $str): void
    {
        if (!$this->lookAhead($str)) {
            throw new RuntimeException(\sprintf(
                'Expected "%s" at position %d, got "%s" in "%s"',
                $str,
                $this->pos,
                \substr($this->input, $this->pos, \strlen($str)),
                $this->input,
            ));
        }
        $this->pos += \strlen($str);
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->len && \in_array($this->input[$this->pos], [' ', "\t", "\n", "\r", '*'], true)) {
            $this->pos++;
        }
    }
}
