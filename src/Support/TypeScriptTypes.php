<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

/**
 * What a TypeScript type a project's config gives an alias (`typeAliases`) says, read as text: the
 * config holds TypeScript, not a PHPDoc type TypeBridge parses.
 */
final class TypeScriptTypes
{
    /** Types that never admit null, whatever they are given. */
    private const array NEVER_NULL = [
        'string', 'number', 'boolean', 'bigint', 'symbol', 'object', 'undefined', 'void', 'never', 'true', 'false',
        'Array', 'ReadonlyArray', 'Record', 'Promise', 'Map', 'Set', 'ReadonlyMap', 'ReadonlySet', 'WeakMap', 'WeakSet',
        'Date', 'RegExp', 'Error', 'Function', 'Object', 'String', 'Number', 'Boolean', 'BigInt', 'Symbol',
        'PropertyKey', 'NonNullable', 'Uppercase', 'Lowercase', 'Capitalize', 'Uncapitalize',
        // Each maps the keys it is given to an object, whatever it is given.
        'Pick', 'Omit',
    ];

    /**
     * Whether the type admits null, as `null extends T` holds in TypeScript — as AsQuery<T> reads it:
     * `null`, `unknown` or `any` among the members of its union, read outside brackets and quotes; a
     * member in brackets read inside them; an intersection where each of its parts does, or one is
     * `any`, which an intersection with it is; another of
     * the config's aliases as it is declared. A literal, an object, a list, a tuple or a function —
     * the whole type, when an arrow is its top level: `() => string | null` returns the union — and a
     * built-in that never admits null, do not.
     *
     * Null where the text can't say: a generic it doesn't know, a conditional type, an indexed access
     * or a `typeof` — what tsc would have to evaluate — unless another member admits null outright.
     *
     * @param array<string, string> $aliases the config's aliases, name => TypeScript, to read a name by
     * @param array<string, true>   $reading the aliases being read: one that names itself says nothing
     */
    public static function admitsNull(string $type, array $aliases = [], array $reading = []): ?bool
    {
        $type = trim($type);
        $top = self::topLevel($type);
        // A conditional — its `?` ahead of any arrow, which may be one of its branches — is what tsc
        // would have to evaluate; a function — its arrow ahead, a conditional maybe its return — is
        // none of its return's nulls.
        $conditional = strpos($top, '?');
        $arrow = strpos($top, '=>');
        if (false !== $conditional && (false === $arrow || $conditional < $arrow)) {
            return null;
        }
        if (false !== $arrow) {
            return false;
        }

        $admits = false;
        foreach (self::split($type, '|') as $member) {
            $parts = self::split($member, '&');
            $read = 1 === \count($parts) ? self::memberAdmitsNull($member, $aliases, $reading) : self::allAdmitNull($parts, $aliases, $reading);
            if (true === $read) {
                return true;
            }
            $admits = null === $read ? null : $admits;
        }

        return $admits;
    }

    /**
     * Which key the type is, as `Record<K, V>` reads its K, read through the config's aliases and
     * out of brackets: 'any' for every key an array has — a `string` and a `number` both,
     * `PropertyKey`, or anything at all (`any`, `unknown`); 'string' or 'number' for one of them
     * alone; null for any other, a literal's or a template's among them.
     *
     * @param array<string, string> $aliases the config's aliases, name => TypeScript
     *
     * @return 'any'|'string'|'number'|null
     */
    public static function keyKind(string $type, array $aliases = []): ?string
    {
        $members = self::members($type, $aliases, 0);
        if (isset($members['PropertyKey']) || isset($members['any']) || isset($members['unknown']) || (isset($members['string']) && isset($members['number']))) {
            return 'any';
        }

        return match (array_keys($members)) {
            ['string'] => 'string',
            ['number'] => 'number',
            default => null,
        };
    }

    /**
     * The TypeScript a project-wide alias stands for, through any alias it names in turn; null where
     * the type is no such alias.
     *
     * @param array<string, string> $aliases the config's aliases, name => TypeScript
     */
    public static function standsFor(string $type, array $aliases, int $depth = 0): ?string
    {
        $standsFor = $depth > 8 ? null : $aliases[trim($type)] ?? null;

        return null === $standsFor ? null : self::standsFor($standsFor, $aliases, $depth + 1) ?? $standsFor;
    }

    /**
     * The JSON type a TypeScript type is, where it is a primitive JSON says: `string`, `number` or
     * `boolean`; null for anything else.
     */
    public static function jsonType(string $type): ?string
    {
        return match (trim($type)) {
            'string' => 'string',
            'number' => 'number',
            'boolean' => 'boolean',
            default => null,
        };
    }

    /**
     * Whether the type needs brackets to be an operand — the element of a list, `(A | B)[]`: a union,
     * an intersection, a function or a conditional at its top level, or a type an operator leads
     * (`keyof T[]` is `keyof (T[])`).
     */
    public static function needsBrackets(string $type): bool
    {
        $type = trim($type);

        return 1 === preg_match('/[|&?]|=>/', self::topLevel($type)) || 1 === preg_match('/^(keyof|typeof|readonly|unique|new|infer)\b/', $type);
    }

    /**
     * @param list<string>          $parts
     * @param array<string, string> $aliases
     * @param array<string, true>   $reading
     */
    private static function allAdmitNull(array $parts, array $aliases, array $reading): ?bool
    {
        // `any & T` is `any`, whatever T is.
        if (\in_array('any', $parts, true)) {
            return true;
        }

        $all = true;
        foreach ($parts as $part) {
            $read = self::admitsNull($part, $aliases, $reading);
            if (false === $read) {
                return false;
            }
            $all = null === $read ? null : $all;
        }

        return $all;
    }

    /**
     * @param array<string, string> $aliases
     * @param array<string, true>   $reading
     */
    private static function memberAdmitsNull(string $member, array $aliases, array $reading): ?bool
    {
        if (\in_array($member, ['null', 'unknown', 'any'], true)) {
            return true;
        }
        // In brackets, whole: its union, intersection and arrow are split off at the top level already.
        if (str_starts_with($member, '(') && str_ends_with($member, ')')) {
            return self::admitsNull(substr($member, 1, -1), $aliases, $reading);
        }
        if ('' === $member || str_contains('\'"`[{-0123456789', $member[0]) || str_ends_with($member, '[]') || 1 === preg_match('/^(keyof|readonly|unique)\s/', $member)) {
            return false;
        }
        if (1 !== preg_match('/^([A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*)(<.*>)?$/s', $member, $reference)) {
            return null;
        }

        $name = $reference[1];
        if (!isset($reference[2]) && isset($aliases[$name])) {
            return isset($reading[$name]) ? null : self::admitsNull($aliases[$name], $aliases, [...$reading, $name => true]);
        }

        return \in_array($name, self::NEVER_NULL, true) ? false : null;
    }

    /**
     * The JSON Schema a type is, read through the config's aliases and out of brackets, where JSON
     * can say it: `string`, `number` or `boolean`, literals of them — `'en' | 'fr'` the values it
     * allows — and a union of those. A `null` among them is passed over: whether a value may be null
     * is its reader's to say. Null for any other type.
     *
     * @param array<string, string> $aliases the config's aliases, name => TypeScript
     *
     * @return array<string, mixed>|null
     */
    public static function jsonSchema(string $type, array $aliases = []): ?array
    {
        $types = [];
        $values = [];
        foreach (array_keys(self::members($type, $aliases, 0)) as $member) {
            $member = (string) $member;
            $json = self::jsonType($member);
            $literal = null === $json ? self::literal($member) : null;
            if (null !== $json) {
                $types[$json] = true;
            } elseif (null !== $literal) {
                $values[] = $literal[0];
            } elseif ('null' !== $member) {
                return null;
            }
        }
        // A literal of a type the union takes whole is one of it already.
        $values = array_values(array_filter($values, static fn (string|int|float|bool $value): bool => !isset($types[match (true) {
            \is_string($value) => 'string',
            \is_bool($value) => 'boolean',
            default => 'number',
        }])));
        $schemas = [...array_map(static fn (string $json): array => ['type' => $json], array_keys($types)), ...([] === $values ? [] : [['enum' => $values]])];

        return match (\count($schemas)) {
            0 => null,
            1 => $schemas[0],
            default => ['anyOf' => $schemas],
        };
    }

    /**
     * A union's members, each alias among them and each one in brackets read for its own.
     *
     * @param array<string, string> $aliases
     *
     * @return array<string, true>
     */
    private static function members(string $type, array $aliases, int $depth): array
    {
        $members = [];
        foreach (self::split(trim($type), '|') as $member) {
            $inner = $depth > 8 ? null : (self::inBrackets($member) ?? $aliases[$member] ?? null);
            if (null !== $inner) {
                $members += self::members($inner, $aliases, $depth + 1);
            } elseif ('' !== $member) {
                $members[$member] = true;
            }
        }

        return $members;
    }

    /**
     * The names a type uses — `Date`, `Brand`, `PropertyKey` in `Brand<Date> | PropertyKey` — but
     * for what is quoted, an object's keys and a member read off another (`A.B`'s `B`).
     *
     * @return list<string>
     */
    public static function namesIn(string $type): array
    {
        $unquoted = preg_replace('/([\'"`])(?:\\\\.|(?!\1).)*\1/s', "''", $type) ?? $type;
        preg_match_all('/(?<![\w$.])[A-Za-z_$][\w$]*(?![\w$]|\s*\??\s*:)/', $unquoted, $names);

        return array_values(array_unique($names[0]));
    }

    /**
     * The value a literal is — `'en'`, `"en"`, `1`, `-1.5`, `true` — in a list of one; null for one
     * this can't read, a quoted one with an escape in it among them.
     *
     * @return array{string|int|float|bool}|null
     */
    private static function literal(string $member): ?array
    {
        return match (true) {
            1 === preg_match('/^(?|\'([^\'\\\\]*)\'|"([^"\\\\]*)")$/', $member, $quoted) => [$quoted[1]],
            1 === preg_match('/^-?\d+$/', $member) => [(int) $member],
            1 === preg_match('/^-?\d*\.\d+$/', $member) => [(float) $member],
            'true' === $member, 'false' === $member => ['true' === $member],
            default => null,
        };
    }

    /**
     * What a type wholly in brackets holds: `(string | number)`, not `(A) & (B)`; null for a type
     * that isn't.
     */
    private static function inBrackets(string $type): ?string
    {
        return str_starts_with($type, '(') && str_ends_with($type, ')') && '' === trim(substr(self::topLevel($type), 1, -1)) ? substr($type, 1, -1) : null;
    }

    /**
     * The type's parts at its top level — outside brackets and quotes — split at each separator.
     *
     * @return list<string>
     */
    private static function split(string $type, string $separator): array
    {
        $parts = [''];
        foreach (self::characters($type) as [$character, $depth]) {
            if (0 === $depth && $separator === $character) {
                $parts[] = '';

                continue;
            }
            $parts[array_key_last($parts)] .= $character;
        }

        return array_map(trim(...), $parts);
    }

    /**
     * The type with what is inside brackets and quotes blanked out: its top level.
     */
    private static function topLevel(string $type): string
    {
        $top = '';
        foreach (self::characters($type) as [$character, $depth]) {
            $top .= 0 === $depth ? $character : ' ';
        }

        return $top;
    }

    /**
     * Each character with the depth it is read at: inside brackets or quotes, deeper than 0. A
     * bracket is at the depth outside it, and an arrow's `=>` closes nothing.
     *
     * @return iterable<int, array{string, int}>
     */
    private static function characters(string $type): iterable
    {
        $depth = 0;
        $quote = null;
        $escaped = false;
        $previous = '';
        foreach (str_split($type) as $index => $character) {
            if (null !== $quote) {
                yield $index => [$character, $depth + 1];
                // A backslash escapes the character after it, another backslash included.
                $quote = $character === $quote && !$escaped ? null : $quote;
                $escaped = '\\' === $character && !$escaped;
            } elseif (str_contains('\'"`', $character)) {
                $quote = $character;
                yield $index => [$character, $depth + 1];
            } elseif (str_contains('<([{', $character)) {
                yield $index => [$character, $depth++];
            } elseif (str_contains(')]}', $character) || ('>' === $character && '=' !== $previous)) {
                yield $index => [$character, --$depth];
            } else {
                yield $index => [$character, $depth];
            }
            $previous = $character;
        }
    }
}
