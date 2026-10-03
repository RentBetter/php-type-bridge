<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

/**
 * A request's `?include=` or `?expand=` as a tree: comma-separated dotted paths, where each path
 * implies every prefix — `checks.lastRun.debug` includes `checks.lastRun` and `checks`.
 *
 * In an include, `*` as the last segment stands for every optional key one level down: `checks.*`
 * opens each check's optional keys, not theirs in turn. It is not allowed at the root, where a
 * request names the parts of the response it wants, and not mid-path, where it would be hard to
 * read and to validate. An expand has no `*` at all: each reference it names is a query, so it
 * names each one. There is no `[]`: a list's items sit at the same point in the tree as the list.
 *
 * An expand's segment can carry a field list — `checks.definition(name,cadence)` — narrowing what
 * is there to those keys and its `id`. A field can carry a list of its own, which reads as the
 * dotted path: `property.account(status(name))` is `property.account(status)` and
 * `property.account.status(name)`. The same segment given twice merges its lists, and one that
 * ends a path without a list asks for the whole thing.
 */
final class IncludeTree
{
    private const string FIELD = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** @var array<string, true>|null the keys a field list named, or null when none did */
    private ?array $fields = null;

    /** Whether a path ended here without a field list, asking for the whole record. */
    private bool $whole = false;

    /**
     * @param array<string, self> $children
     */
    private function __construct(
        private array $children = [],
        private bool $all = false,
    ) {}

    /**
     * @throws InvalidIncludeException a path with an empty segment, a misplaced `*`, or a field list
     */
    public static function parse(string $include): self
    {
        return self::parsePaths($include, 'include');
    }

    /**
     * @throws InvalidIncludeException a path with an empty segment, a `*`, or a malformed field list
     */
    public static function parseExpand(string $expand): self
    {
        return self::parsePaths($expand, 'expand');
    }

    /**
     * @param 'include'|'expand' $param
     */
    private static function parsePaths(string $paths, string $param): self
    {
        $root = new self();

        foreach (self::split($paths, ',', $param) as $path) {
            $segments = self::split($path, '.', $param, keepEmpty: true);
            $last = \count($segments) - 1;
            $node = $root;

            foreach ($segments as $i => $segment) {
                if ('' === $segment) {
                    throw new InvalidIncludeException($param, \sprintf('`%s` has an empty segment.', $path));
                }

                if ('*' === $segment) {
                    if ('expand' === $param) {
                        throw new InvalidIncludeException($param, \sprintf('`%s`: name each reference to expand; each one is a query, so there is no `*`.', $path));
                    }
                    if (0 === $i) {
                        throw new InvalidIncludeException($param, 'A `*` cannot stand at the root: name the parts of the response you want.');
                    }
                    if ($i !== $last) {
                        throw new InvalidIncludeException($param, \sprintf('`%s`: a `*` can only be the last segment.', $path));
                    }
                    $node->all = true;

                    break;
                }

                [$key, $list] = self::segment($segment, $path, $param);
                $node = $node->children[$key] ??= new self();

                if (null !== $list) {
                    $node->narrowTo($list, $path);
                } elseif ($i === $last) {
                    $node->whole = true;
                }
            }
        }

        return $root;
    }

    /**
     * A segment's key, and the field list it carries — what is between its parentheses — if any.
     *
     * @param 'include'|'expand' $param
     *
     * @return array{string, string|null}
     */
    private static function segment(string $segment, string $path, string $param): array
    {
        $open = strpos($segment, '(');
        if (false === $open) {
            return [$segment, null];
        }

        if ('include' === $param) {
            throw new InvalidIncludeException($param, \sprintf('`%s`: a field list narrows what an expand sends, so it goes on an `expand` path.', $path));
        }

        $key = substr($segment, 0, $open);
        if ('' === $key || !str_ends_with($segment, ')')) {
            throw self::malformed($path);
        }

        return [$key, substr($segment, $open + 1, -1)];
    }

    /**
     * Adds a field list to this node: each field, and any list a field carries of its own, on
     * the child it names.
     */
    private function narrowTo(string $list, string $path): void
    {
        $fields = self::split($list, ',', 'expand');
        if ([] === $fields) {
            throw self::malformed($path);
        }

        foreach ($fields as $field) {
            [$key, $inner] = self::segment($field, $path, 'expand');
            if (1 !== preg_match(self::FIELD, $key)) {
                throw self::malformed($path);
            }

            $this->fields[$key] = true;
            if (null !== $inner) {
                ($this->children[$key] ??= new self())->narrowTo($inner, $path);
            }
        }
    }

    private static function malformed(string $path): InvalidIncludeException
    {
        return new InvalidIncludeException('expand', \sprintf('`%s`: a field list is a key followed by field names in parentheses, as in `checks.definition(name,cadence)`; a field can carry its own, as in `status(name)`.', $path));
    }

    /**
     * Splits on a separator outside parentheses, so `a(b,c),d` is two paths, not three.
     *
     * @param 'include'|'expand' $param
     *
     * @return list<string> trimmed; empty pieces dropped unless kept
     */
    private static function split(string $value, string $separator, string $param, bool $keepEmpty = false): array
    {
        $pieces = [];
        $depth = 0;
        $piece = '';

        foreach (str_split($value) as $char) {
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char && --$depth < 0) {
                break;
            }

            if ($separator === $char && 0 === $depth) {
                $pieces[] = trim($piece);
                $piece = '';
            } else {
                $piece .= $char;
            }
        }

        if (0 !== $depth) {
            throw new InvalidIncludeException($param, \sprintf('`%s` has unbalanced parentheses.', trim($value)));
        }
        $pieces[] = trim($piece);

        return $keepEmpty ? $pieces : array_values(array_filter($pieces, static fn (string $piece): bool => '' !== $piece));
    }

    /**
     * The subtree under a key: named explicitly, or covered by a `*` at this level — which
     * opens the key itself, not the optional keys beneath it.
     */
    public function child(string $key): ?self
    {
        return $this->children[$key] ?? ($this->all ? new self() : null);
    }

    public function has(string $key): bool
    {
        return null !== $this->child($key);
    }

    /**
     * The keys named explicitly at this level, which a caller can check against what exists.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(strval(...), array_keys($this->children));
    }

    /**
     * The keys a field list narrowed this node's record to, or null for the whole record.
     *
     * @return list<string>|null
     */
    public function fields(): ?array
    {
        return $this->whole || null === $this->fields ? null : array_map(strval(...), array_keys($this->fields));
    }
}
