<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnusedBaseImportFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\UnusedBaseImportFixtures\Relay\Shape\Relay;

/**
 * Imports `Working` from a class that only imports it itself, for a property that is never sent.
 * Nothing published names it, so it is no matter where a response extending this is emitted.
 *
 * @phpstan-import-type Working from Relay
 */
abstract class AbstractNoteResponse
{
    public string $title = '';

    /** @var Working */
    protected array $working = ['a' => 1];
}
