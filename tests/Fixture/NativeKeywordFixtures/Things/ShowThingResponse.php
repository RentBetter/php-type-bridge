<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NativeKeywordFixtures\Things;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * `object` and `iterable`, spelled natively and in a docblock.
 */
final class ShowThingResponse implements HttpOk
{
    public object $native;

    /** @var object */
    public object $documented;

    public ?object $maybe = null;

    public iterable $items = [];

    /** @var iterable */
    public iterable $documentedItems = [];

    /** @var iterable<string> */
    public iterable $tags = [];

    /** @var iterable<int, bool> */
    public iterable $flags = [];

    /** @var iterable<mixed, string> */
    public iterable $anyKeyed = [];

    public ?iterable $maybeItems = null;

    public iterable|null $alsoMaybeItems = null;

    public iterable|string $itemsOrName = [];
}
