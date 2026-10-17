<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's base whose property names a class: the library's own `Item`, as this file reads it.
 */
abstract class AbstractItemsResponse
{
    /** @var list<Item> */
    public array $items = [];
}
