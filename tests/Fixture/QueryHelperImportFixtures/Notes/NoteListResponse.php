<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryHelperImportFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

final class NoteListResponse implements HttpOk
{
    public function __construct(
        public readonly int $total,
    ) {}
}
