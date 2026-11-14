<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Inherited204Fixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpNoContent;

/** Declares a property of its own, which a 204 never sends. */
final class RestoreNoteResponse implements HttpNoContent
{
    public int $version = 1;
}
