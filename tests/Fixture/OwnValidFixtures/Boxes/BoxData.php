<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

/**
 * @phpstan-type _self = array{
 *     items?: list<ItemData>,
 *     shell?: ShellData,
 *     bags?: list<BagData>,
 * }
 */
final class BoxData
{
    // No cascade of its own: each item's form's `constraints` cascade.
    /** @var list<ItemData> */
    public array $items = [];

    // No cascade: ShellData's own constraints are never validated, but what its `lid`'s form holds is.
    public ?ShellData $shell = null;

    /** @var list<BagData> */
    public array $bags = [];
}
