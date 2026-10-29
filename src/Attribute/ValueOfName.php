<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;
use ReflectionClass;

/**
 * Names the union `value-of<Enum>` emits — `#[ValueOfName('CurrencyCode')]` — so the enum's own
 * name can go to what it serialises to. An enum's `_self` is `{Enum}Data` only to keep it off the
 * union's name; once the union is named otherwise, the `_self` takes the enum's name, as any
 * class's does.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ValueOfName
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * The name an enum's `_self` goes by: the one it takes beside a value union under the enum's own
     * name — `{Enum}Data`, or the project's suffix for it — or the enum's own name, once the union is
     * named otherwise. One rule for the key it is collected under and the name it is declared as.
     */
    public static function shapeName(string $enumClass, string $shortName, string $besideTheUnion): string
    {
        return null === self::of($enumClass) ? $besideTheUnion : $shortName;
    }

    /**
     * The name the enum gives its value union, if it gives one.
     */
    public static function of(string $enumClass): ?self
    {
        if (!enum_exists($enumClass)) {
            return null;
        }

        $attributes = (new ReflectionClass($enumClass))->getAttributes(self::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }
}
