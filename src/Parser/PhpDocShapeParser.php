<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

use RuntimeException;

/**
 * Recursive descent parser for PHPStan array{} shape subset.
 *
 * Grammar:
 *   TypeDef    = Shape | NameRef '&' Shape | NameRef
 *   Shape      = 'array{' Fields '}' | 'array{' Type (',' Type)* ','? '}'   (keyed, or a positional tuple)
 *   Fields     = Field (',' Field)* ','?
 *   Field      = Ident '?'? ':' Type | '?' Ident ':' Type
 *   Type       = Suffixed ('|' Suffixed)*
 *   Suffixed   = SingleType '[]'*
 *   SingleType = '?' SingleType | 'value-of<' ClassName '>' | 'id-of<' ClassName '>' | ScalarType | Literal | Shape | 'list<' Type '>' | Map | NameRef
 *   Map        = 'array<' Type ',' Type '>'
 *   ScalarType = 'string' | 'int' | 'float' | 'bool' | 'mixed' | 'numeric' | 'null'
 *   Literal    = StringLiteral | NumberLiteral | 'true' | 'false'
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
            return $this->parseShapeOrTuple();
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

    /**
     * The right-hand side of an intersection, which must be keyed — `Foo & array{int, int}`
     * has no meaning.
     */
    private function parseShape(): ShapeType
    {
        $shape = $this->parseShapeOrTuple();
        if (!$shape instanceof ShapeType) {
            throw new RuntimeException('Intersection right-hand side must be a keyed shape, not a tuple');
        }

        return $shape;
    }

    /**
     * `array{a: int}` is a keyed shape; `array{int, string}` is a positional tuple. Which one is
     * decided per entry by whether a key precedes the type, and the two cannot be mixed: a
     * partly-keyed array has no TypeScript equivalent.
     */
    private function parseShapeOrTuple(): ShapeType|TupleType
    {
        $this->expect('array{');
        $fields = [];
        $elements = [];

        $this->skipWhitespace();
        while ($this->pos < $this->len && '}' !== $this->input[$this->pos]) {
            if ($this->atKeyedField()) {
                $fields[] = $this->parseField();
            } else {
                $elements[] = $this->parseType();
            }

            $this->skipWhitespace();
            if ($this->pos < $this->len && ',' === $this->input[$this->pos]) {
                $this->pos++;
                $this->skipWhitespace();
            }
        }

        $this->expect('}');

        if ([] !== $fields && [] !== $elements) {
            throw new RuntimeException('array{} entries must be all keyed or all positional, not a mix');
        }

        return [] !== $elements ? new TupleType($elements) : new ShapeType($fields);
    }

    /**
     * Whether the next entry carries a key. Pure lookahead — the position is always restored,
     * so a malformed value still fails inside parseField() with its own message rather than
     * being mistaken for a positional element.
     */
    private function atKeyedField(): bool
    {
        $saved = $this->pos;

        $this->skipWhitespace();
        if ($this->pos < $this->len && '?' === $this->input[$this->pos]) {
            $this->pos++;
            $this->skipWhitespace();
        }

        $keyed = false;
        if (null !== $this->tryParseIdent()) {
            $this->skipWhitespace();
            if ($this->pos < $this->len && '?' === $this->input[$this->pos]) {
                $this->pos++;
                $this->skipWhitespace();
            }
            $keyed = $this->pos < $this->len && ':' === $this->input[$this->pos];
        }

        $this->pos = $saved;

        return $keyed;
    }

    private function parseField(): ShapeField
    {
        $this->skipWhitespace();

        // Optional key, PHPStan's array-shape syntax: fieldName?: type
        //
        // A leading `?fieldName:` was once accepted too. It was dropped: `?` already means
        // nullable, so reading it as an optional key first required lookahead and backtracking
        // to tell `?name: string` from `?string`, and no other tool understood the result.
        $optional = false;
        $fieldName = $this->parseIdent();

        if ($this->pos < $this->len && '?' === $this->input[$this->pos]) {
            $this->pos++;
            $optional = true;
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

        $type = $this->parseSuffixedType();

        // Check for union: type|null or type|type
        $this->skipWhitespace();
        if ($this->pos < $this->len && '|' === $this->input[$this->pos]) {
            $types = [$type];
            while ($this->pos < $this->len && '|' === $this->input[$this->pos]) {
                $this->pos++;
                $this->skipWhitespace();
                $types[] = $this->parseSuffixedType();
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

    /**
     * `T[]` is PHPStan's suffix spelling of `list<T>`, and it stacks — `T[][]` is a list of
     * lists. Handled here rather than in parseSingleType so it applies to every branch.
     */
    private function parseSuffixedType(): ParsedType
    {
        $type = $this->pos < $this->len && '(' === $this->input[$this->pos]
            ? $this->parseGroup()
            : $this->parseSingleType();

        while ($this->pos + 1 < $this->len && '[' === $this->input[$this->pos] && ']' === $this->input[$this->pos + 1]) {
            $this->pos += 2;
            $type = new ListType($type);
        }

        return $type;
    }

    /**
     * A parenthesised group.
     *
     * PHPDoc allows parentheses around any type, and phpstan/phpdoc-parser emits them
     * canonically — around an intersection, and around a union nested inside a shape field:
     * `(Base & array{note: (string | null)})`. They group and mean nothing else, so the inner
     * type is parsed and the parens discarded.
     */
    private function parseGroup(): ParsedType
    {
        $this->pos++;
        $this->skipWhitespace();
        // parseTypeDef, not parseType: a group may hold an intersection, and only the former
        // reads `&`. That is exactly the shape phpdoc-parser emits for `Base & array{...}`.
        $type = $this->parseTypeDef();
        $this->skipWhitespace();
        $this->expect(')');

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

        // array{...} → ShapeType, or TupleType when its entries are positional
        if ($this->lookAhead('array{')) {
            return $this->parseShapeOrTuple();
        }

        // list<T>
        if ($this->lookAhead('list<')) {
            $this->expect('list<');
            $inner = $this->parseType();
            $this->skipWhitespace();
            $this->expect('>');

            return new ListType($inner);
        }

        // array<K, V> → MapType. Sits after the `array{` branch above, so a
        // shape is never mistaken for a map.
        //
        // Only the two-argument form is accepted. PHPStan reads one-argument
        // `array<V>` as an INTEGER-keyed list, so quietly treating it as a
        // string-keyed map would emit a type that lies about the data — better
        // to reject it and make the author write `list<V>`.
        if ($this->lookAhead('array<')) {
            $this->expect('array<');
            $key = $this->parseType();
            $this->skipWhitespace();
            if ($this->pos >= $this->len || ',' !== $this->input[$this->pos]) {
                throw new RuntimeException(\sprintf(
                    'array<> needs a key and a value type at position %d in "%s" — '
                    . 'single-argument array<V> is an integer-keyed list; write list<V> instead.',
                    $this->pos,
                    $this->input,
                ));
            }
            $this->pos++;
            $value = $this->parseType();
            $this->skipWhitespace();
            $this->expect('>');

            return new MapType($key, $value);
        }

        // value-of<ClassName>
        if ($this->lookAhead('value-of<')) {
            $this->expect('value-of<');
            $className = $this->parseClassName();
            $this->skipWhitespace();
            $this->expect('>');

            return new ValueOfType($className);
        }

        // id-of<ClassName>
        if ($this->lookAhead('id-of<')) {
            $this->expect('id-of<');
            $className = $this->parseClassName();
            $this->skipWhitespace();
            $this->expect('>');

            return new IdOfType($className);
        }

        // Quoted string literal: 'draft' or "draft"
        if ($this->pos < $this->len && ("'" === $this->input[$this->pos] || '"' === $this->input[$this->pos])) {
            return new LiteralType($this->parseStringLiteral());
        }

        // Number literal: 42, -1, 3.14
        if ($this->atNumberLiteral()) {
            return $this->parseNumberLiteral();
        }

        // Scalar types
        foreach (['string', 'int', 'float', 'bool', 'mixed', 'numeric', 'null'] as $scalar) {
            if ($this->lookAhead($scalar) && !$this->isIdentChar($this->pos + \strlen($scalar))) {
                $this->pos += \strlen($scalar);

                return new ScalarType($scalar);
            }
        }

        // Boolean literals
        foreach (['true' => true, 'false' => false] as $keyword => $value) {
            if ($this->lookAhead($keyword) && !$this->isIdentChar($this->pos + \strlen($keyword))) {
                $this->pos += \strlen($keyword);

                return new LiteralType($value);
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

    private function parseStringLiteral(): string
    {
        $quote = $this->input[$this->pos];
        $this->pos++;

        $value = '';
        while ($this->pos < $this->len && $this->input[$this->pos] !== $quote) {
            if ('\\' === $this->input[$this->pos] && $this->pos + 1 < $this->len) {
                $this->pos++;
            }
            $value .= $this->input[$this->pos];
            $this->pos++;
        }

        if ($this->pos >= $this->len) {
            throw new RuntimeException(\sprintf(
                'Unterminated string literal starting at position %d in "%s"',
                $this->pos,
                $this->input,
            ));
        }

        $this->pos++; // closing quote

        return $value;
    }

    private function atNumberLiteral(): bool
    {
        if ($this->pos >= $this->len) {
            return false;
        }

        if (ctype_digit($this->input[$this->pos])) {
            return true;
        }

        return '-' === $this->input[$this->pos]
            && $this->pos + 1 < $this->len
            && ctype_digit($this->input[$this->pos + 1]);
    }

    private function parseNumberLiteral(): LiteralType
    {
        $start = $this->pos;
        if ('-' === $this->input[$this->pos]) {
            $this->pos++;
        }
        while ($this->pos < $this->len && ctype_digit($this->input[$this->pos])) {
            $this->pos++;
        }

        $isFloat = false;
        if (
            $this->pos + 1 < $this->len
            && '.' === $this->input[$this->pos]
            && ctype_digit($this->input[$this->pos + 1])
        ) {
            $isFloat = true;
            $this->pos++;
            while ($this->pos < $this->len && ctype_digit($this->input[$this->pos])) {
                $this->pos++;
            }
        }

        $raw = \substr($this->input, $start, $this->pos - $start);

        return new LiteralType($isFloat ? (float) $raw : (int) $raw);
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
