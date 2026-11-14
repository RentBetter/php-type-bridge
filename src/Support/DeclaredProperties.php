<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PhpToken;

/**
 * The properties a class or trait declares in its own body — a promoted constructor param among
 * them — rather than takes from a trait it uses. Reflection can't tell the two apart where they
 * agree: PHP lets a class redeclare a trait's property alike, doc comment and all, and names the
 * class as declaring it either way. Its source can. The class is found where Reflection says it
 * starts, so which class a file declares, and in which namespace, is Reflection's to say alone.
 */
final class DeclaredProperties
{
    private const array MODIFIERS = ['public', 'protected', 'private', 'var', 'readonly', 'static', 'final', 'abstract', 'public(set)', 'protected(set)', 'private(set)'];

    /**
     * The properties the body of the class starting at `$startLine` of the source declares.
     *
     * @return array<string, true>
     */
    public static function inSource(string $source, int $startLine): array
    {
        $tokens = array_values(PhpToken::tokenize($source));
        $body = self::bodyOpening($tokens, $startLine);
        if (null === $body) {
            return [];
        }

        $declared = [];
        $depth = 0;
        $count = \count($tokens);
        for ($i = $body; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (\in_array($token->text, ['{', '${'], true)) {
                ++$depth;
            } elseif ('}' === $token->text) {
                if (0 === --$depth) {
                    break;
                }
            } elseif (1 === $depth && $token->is(T_VARIABLE) && self::isDeclaration($tokens, $i)) {
                $declared[substr($token->text, 1)] = true;
            }
        }

        return $declared;
    }

    /**
     * Where the body opens of the class declared from `$startLine` on: the first `{` after a
     * declaration's keyword — one its name follows, not `Foo::class` in an attribute.
     *
     * @param list<PhpToken> $tokens
     */
    private static function bodyOpening(array $tokens, int $startLine): ?int
    {
        $count = \count($tokens);
        $declared = false;
        for ($i = 0; $i < $count; ++$i) {
            if (!$declared) {
                $declared = $tokens[$i]->line >= $startLine
                    && $tokens[$i]->is([T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE])
                    && true === self::nextSignificant($tokens, $i)?->is(T_STRING);
            } elseif ('{' === $tokens[$i]->text) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Whether the variable at `$at` in a class body is a property it declares: a modifier before it,
     * past its type — not a method's param, which `function` and the method's name come before.
     *
     * @param list<PhpToken> $tokens
     */
    private static function isDeclaration(array $tokens, int $at): bool
    {
        for ($i = $at - 1; $i >= 0; --$i) {
            $token = $tokens[$i];
            if ($token->isIgnorable()
                || $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_ARRAY, T_CALLABLE])
                || \in_array($token->text, ['?', '|', '&', '(', ')'], true)) {
                continue;
            }

            return \in_array(strtolower($token->text), self::MODIFIERS, true);
        }

        return false;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function nextSignificant(array $tokens, int $after): ?PhpToken
    {
        $count = \count($tokens);
        for ($i = $after + 1; $i < $count; ++$i) {
            if (!$tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }

        return null;
    }
}
