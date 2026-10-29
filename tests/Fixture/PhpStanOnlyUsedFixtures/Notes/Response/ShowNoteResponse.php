<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyUsedFixtures\Notes\Response;

use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * A published property typed with an alias marked as never sent: a contradiction to fail on.
 *
 * @phpstan-type Row = array{at: \DateTimeImmutable}
 */
#[PhpStanOnly(['Row'])]
final class ShowNoteResponse implements HttpOk
{
    /**
     * @param list<Row> $rows
     */
    public function __construct(
        public readonly array $rows,
    ) {}
}
