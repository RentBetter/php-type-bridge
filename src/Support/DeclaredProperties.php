<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PhpToken;
use ReflectionClass;

/**
 * The properties a class or trait declares in its own body — a promoted constructor param among
 * them — rather than takes from a trait it uses. Reflection can't tell the two apart where they
 * agree: PHP lets a class redeclare a trait's property alike, doc comment and all, and names the
 * class as declaring it either way. Its source can.
 */
final class DeclaredProperties
{
    private const array MODIFIERS = ['public', 'protected', 'private', 'var', 'readonly', 'static', 'final', 'abstract', 'public(set)', 'protected(set)', 'private(set)'];

    /** @var array<string, array<string, true>> by class, the properties its body declares */
    private static array $declared = [];

    /**
     * @param ReflectionClass<object> $class
     */
    public static function declares(ReflectionClass $class, string $property): bool
    {
        return isset((self::$declared[$class->getName()] ??= self::read($class))[$property]);
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return array<string, true>
     */
    private static function read(ReflectionClass $class): array
    {
        $file = $class->getFileName();
        $source = false === $file || !is_file($file) ? false : file_get_contents($file);
        if (false === $source) {
            return [];
        }

        $tokens = array_values(PhpToken::tokenize($source));
        $body = self::bodyOpening($tokens, $class);
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
     * Where the class's body opens: the first `{` after its keyword and its name, from its line on.
     *
     * @param list<PhpToken>          $tokens
     * @param ReflectionClass<object> $class
     */
    private static function bodyOpening(array $tokens, ReflectionClass $class): ?int
    {
        $count = \count($tokens);
        $named = false;
        for ($i = 0; $i < $count; ++$i) {
            if (!$named) {
                $named = $tokens[$i]->line >= $class->getStartLine()
                    && $tokens[$i]->is([T_CLASS, T_TRAIT, T_ENUM, T_INTERFACE])
                    && $class->getShortName() === self::nextSignificant($tokens, $i)?->text;
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
