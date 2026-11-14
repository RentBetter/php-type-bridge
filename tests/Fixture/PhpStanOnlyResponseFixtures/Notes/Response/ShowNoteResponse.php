<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Attribute\PhpStanOnly;

/**
 * Its own PHPStan-only alias, beside its base's: neither is emitted, and neither is an import.
 *
 * @phpstan-type Draft = array{since: \DateTimeImmutable}
 */
#[PhpStanOnly(['Draft'])]
final class ShowNoteResponse extends AbstractWorkingResponse
{
}
