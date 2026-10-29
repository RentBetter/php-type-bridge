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
    ];

    /**
     * Whether the type admits null, as `null extends T` holds in TypeScript — as AsQuery<T> reads it:
     * `null`, `unknown` or `any` among the members of its union, read outside brackets and quotes; a
     * member in brackets read inside them; an intersection where each of its parts does; another of
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
        if (str_contains($top, '=>')) {
            return false;
        }
        if (str_contains($top, '?')) {
            return null;
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
