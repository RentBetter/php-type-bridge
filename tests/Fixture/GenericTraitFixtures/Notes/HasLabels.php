<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

/**
 * A trait using another, passing its own param on.
 *
 * @template L
 */
trait HasLabels
{
    /** @use HasRows<L> */
    use HasRows;
}
