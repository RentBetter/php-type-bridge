<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

/**
 * A generic base passing its own param on to the trait it uses.
 *
 * @template R
 */
abstract class RowsBase
{
    /** @use HasRows<R> */
    use HasRows;
}
