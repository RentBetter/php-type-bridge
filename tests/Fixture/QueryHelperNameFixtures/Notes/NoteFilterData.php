<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryHelperNameFixtures\Notes;

/**
 * Declares a type under the name the module's query helper is emitted as.
 *
 * @phpstan-type AsQuery = array{x: int}
 * @phpstan-type _self = array{q?: string}
 */
final class NoteFilterData
{
    public ?string $q = null;
}
