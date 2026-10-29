<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumOnlyModuleFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\EnumOnlyModuleFixtures\Common\Enum\NoteState;
use PTGS\TypeBridge\Tests\Fixture\EnumOnlyModuleFixtures\Common\Enum\Status;

final class ShowNoteResponse implements HttpOk
{
    /** @var value-of<NoteState> */
    public string $state = 'open';

    /** @var value-of<Status> */
    public string $status = 'active';
}
