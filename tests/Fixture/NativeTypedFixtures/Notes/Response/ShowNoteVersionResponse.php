<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Common\Response\NoteVersion;

final class ShowNoteVersionResponse extends NoteVersion implements HttpOk
{
    public ?parent $previous = null;
}
