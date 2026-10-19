<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

/**
 * What a TypeScript type a project's config gives an alias (`typeAliases`) says, read as text: the
 * config holds TypeScript, not a PHPDoc type TypeBridge parses.
 */
final class TypeScriptTypes
{
    /**
     * Whether the type admits null, as `null extends T` holds in TypeScript: `null`, `unknown` or
     * `any` among the members of its union, read outside any brackets or quotes — `string | null`,
     * not `Array<string | null>` — a member in brackets read inside them, and an arrow's `=>` taken
     * for the arrow it is, not a closing bracket: `(() => void) | null` does.
     */
    public static function admitsNull(string $type): bool
    {
        foreach (self::members($type) as $member) {
            if (\in_array($member, ['null', 'unknown', 'any'], true)) {
                return true;
            }
            if (str_starts_with($member, '(') && self::closes($member) && self::admitsNull(substr($member, 1, -1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a member's opening bracket is the one its last character closes: `(a | b)`, not
     * `(a) | (b)` — read as members already — nor `(a)[]`.
     */
    private static function closes(string $member): bool
    {
        if (!str_ends_with($member, ')')) {
            return false;
        }
        $depth = 0;
        $previous = '';
        foreach (str_split(substr($member, 0, -1)) as $character) {
            if (str_contains('<([{', $character)) {
                ++$depth;
            } elseif (str_contains(')]}', $character) || ('>' === $character && '=' !== $previous)) {
                --$depth;
                if (0 === $depth) {
                    return false;
                }
            }
            $previous = $character;
        }

        return 1 === $depth;
    }

    /**
     * The members of a union, read outside brackets and quotes.
     *
     * @return list<string>
     */
    private static function members(string $type): array
    {
        $members = [];
        $member = '';
        $depth = 0;
        $quote = null;
        $previous = '';
        foreach (str_split($type) as $character) {
            if (null !== $quote) {
                $quote = $character === $quote ? null : $quote;
            } elseif (str_contains('\'"`', $character)) {
                $quote = $character;
            } elseif (str_contains('<([{', $character)) {
                ++$depth;
            } elseif (str_contains(')]}', $character) || ('>' === $character && '=' !== $previous)) {
                --$depth;
            } elseif ('|' === $character && 0 === $depth) {
                $members[] = trim($member);
                $member = '';
                $previous = $character;

                continue;
            }
            $member .= $character;
            $previous = $character;
        }
        $members[] = trim($member);

        return $members;
    }
}
