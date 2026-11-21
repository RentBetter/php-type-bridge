<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\StatusOnlyFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

final class NoteResponse implements HttpOk
{
    public string $id = '';
}
