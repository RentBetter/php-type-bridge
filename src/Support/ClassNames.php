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

    /**
     * The class a name written in a file means, as PHP resolves it: the name in full where it starts
     * with `\`; through the file's `use` statements by its first part (`Input\AddressData` where the
     * file imports `…\Input`); else in the file's namespace. Whether a class of that name exists is
     * not asked.
     *
     * @param array<string, string> $classImports the file's `use` statements, by the name each gives
     */
    public static function resolve(string $written, array $classImports, ?string $namespace): string
    {
        if (str_starts_with($written, '\\')) {
            return ltrim($written, '\\');
        }
        $first = explode('\\', $written, 2);
        if (isset($classImports[$first[0]])) {
            return $classImports[$first[0]] . (isset($first[1]) ? '\\' . $first[1] : '');
        }

        return null === $namespace || '' === $namespace ? $written : $namespace . '\\' . $written;
    }

    /**
     * Whether the name, written in a file, is resolved through one of its `use` statements.
     *
     * @param array<string, string> $classImports
     */
    public static function imported(string $written, array $classImports): bool
    {
        return !str_starts_with($written, '\\') && isset($classImports[explode('\\', $written, 2)[0]]);
    }
}
