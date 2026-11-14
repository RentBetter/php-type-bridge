<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PhpToken;

/**
 * The properties each class or trait of a file declares in its own body — a promoted constructor
 * param among them — rather than takes from a trait it uses. Reflection can't tell the two apart
 * where they agree: PHP lets a class redeclare a trait's property alike, doc comment and all, and
 * names the class as declaring it either way. Its source can. Read in the one pass over a file's
 * tokens ({@see PhpDocTypeHelper::readFile()}).
 */
final class DeclaredProperties
{
    private const array MODIFIERS = ['public', 'protected', 'private', 'var', 'readonly', 'static', 'final', 'abstract', 'public(set)', 'protected(set)', 'private(set)'];

    /**
     * By class, in full, the properties its body declares.
     *
     * @param list<PhpToken> $tokens    the file's
     * @param string         $namespace the namespace it declares, '' for none
     *
     * @return array<string, array<string, true>>
     */
    public static function inTokens(array $tokens, string $namespace): array
    {
        $declared = [];
        $count = \count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            if (!$tokens[$i]->is([T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE])) {
                continue;
            }
            // A declaration's keyword is followed by its name: not `Foo::class`, nor `new class`.
            $name = self::nextSignificant($tokens, $i);
            if (null === $name || !$tokens[$name]->is(T_STRING)) {
                continue;
            }

            $class = ('' === $namespace ? '' : $namespace . '\\') . $tokens[$name]->text;
            $declared[$class] = [];
            for ($i = $name; $i < $count && '{' !== $tokens[$i]->text; ++$i) {
            }
            $depth = 0;
            for (; $i < $count; ++$i) {
                $token = $tokens[$i];
                if (\in_array($token->text, ['{', '${'], true)) {
                    ++$depth;
                } elseif ('}' === $token->text) {
                    if (0 === --$depth) {
                        break;
                    }
                } elseif (1 === $depth && $token->is(T_VARIABLE) && self::isDeclaration($tokens, $i)) {
                    $declared[$class][substr($token->text, 1)] = true;
                }
            }
        }

        return $declared;
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
     * Where the next token that isn't whitespace or a comment is.
     *
     * @param list<PhpToken> $tokens
     */
    private static function nextSignificant(array $tokens, int $after): ?int
    {
        $count = \count($tokens);
        for ($i = $after + 1; $i < $count; ++$i) {
            if (!$tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }
}
