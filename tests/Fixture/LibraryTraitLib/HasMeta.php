<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryTraitLib;

/**
 * A library's trait, outside the source tree read: its property is on the wire all the same.
 */
trait HasMeta
{
    /** @var array{page: int} */
    public array $meta = ['page' => 1];
}
