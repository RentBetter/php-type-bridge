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
     * Whether the type admits null: `null` among the members of its union, read outside any
     * brackets or quotes — `string | null`, not `Array<string | null>` — and taking an arrow's `=>`
     * for the arrow it is, not a closing bracket: `(() => void) | null` does.
     */
    public static function admitsNull(string $type): bool
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

        return \in_array('null', $members, true);
    }
}
