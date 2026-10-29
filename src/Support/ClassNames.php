<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

/**
 * A class name's parts, split one way wherever one is asked for.
 */
final class ClassNames
{
    /**
     * The name without its namespace: `App\Notes\Note` is `Note`.
     */
    public static function short(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }

    /**
     * The namespace the name is in: `App\Notes\Note`'s is `App\Notes`; null for a name in none.
     */
    public static function namespaceOf(string $class): ?string
    {
        $position = strrpos($class, '\\');

        return false === $position ? null : substr($class, 0, $position);
    }
}
