<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

/**
 * @template T
 * @template U = int
 */
trait HasRows
{
    /** @var list<T> */
    public array $rows = [];

    /** @var U */
    public mixed $total = 0;
}
