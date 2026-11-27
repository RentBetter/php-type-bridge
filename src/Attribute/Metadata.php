<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;

/**
 * A response property that says something about the response, rather than being part of what was
 * asked for: the page a list was cut at, say. It is sent, and published, under its name with a `$`
 * before it — `#[Metadata] public array $pagination` is the body's `$pagination` — which no
 * property can be named itself, so it never collides with one.
 *
 * Only its key changes: it is typed, and written out, as any other property is.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Metadata
{
    /** What a metadata property's name is sent behind. */
    public const string PREFIX = '$';

    /**
     * The key a metadata property of this name is sent under.
     */
    public static function key(string $property): string
    {
        return self::PREFIX . $property;
    }
}
