<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PhpToken;

/**
 * Reading PHP's tokens past what is no part of the code: whitespace and comments.
 */
final class Tokens
{
    /**
     * The first token from `$at` on, in the direction `$step` goes, that isn't whitespace or a
     * comment; null where there is none.
     *
     * @param array<PhpToken> $tokens
     */
    public static function significant(array $tokens, int $at, int $step = 1): ?PhpToken
    {
        for ($i = $at + $step; isset($tokens[$i]); $i += $step) {
            if (!$tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }

        return null;
    }
}
