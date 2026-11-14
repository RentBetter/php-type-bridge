<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IncludeQueryClashFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

final class ShowNoteResponse implements HttpOk
{
    public string $id = '';
}
