<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Inherited204Fixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\Inherited204Fixtures\Notes\Enum\NoteState;

abstract class AbstractVersionedResponse
{
    public int $version = 1;

    public NoteState $state = NoteState::Open;
}
