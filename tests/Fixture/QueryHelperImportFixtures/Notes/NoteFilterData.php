<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryHelperImportFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\QueryHelperImportFixtures\Common\CommonShapes;

/**
 * Imports a type under the name the module's query helper is emitted as.
 *
 * @phpstan-import-type AsQuery from CommonShapes
 * @phpstan-type Uses = array{x: AsQuery}
 * @phpstan-type _self = array{q?: string}
 */
final class NoteFilterData
{
    public ?string $q = null;
}
