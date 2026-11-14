<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryTraitLib;

use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\Item;

/**
 * A library's trait whose property names a class: `Item` as this file's `use` reads it.
 */
trait HasItems
{
    /** @var list<Item> */
    public array $items = [];
}
