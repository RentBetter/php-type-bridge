<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\StatusOnlyFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * Typed with an alias imported from a class that is nowhere: its TypeScript fails, saying so. No
 * tool answers with it.
 *
 * @phpstan-import-type Legacy from \Vendor\Gone\LegacyShapes
 */
final class LegacyNoteResponse implements HttpOk
{
    /** @var Legacy */
    public array $legacy = [];
}
