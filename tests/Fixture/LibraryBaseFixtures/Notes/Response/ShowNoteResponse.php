<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class ShowNoteResponse extends AbstractNoteResponse implements HttpOk
{
    public function __construct(
        public readonly string $id,
    ) {}
}
