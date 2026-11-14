<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SharedFileTraitFixtures\Notes;

/**
 * @template T
 */
trait HasItems
{
    /** @var list<T> */
    public array $items = [];
}
