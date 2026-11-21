<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Deprecated204ClashFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpNoContent;

/** Declares a property of its own: under a prefix, its old name is kept as a deprecated alias. */
final class RestoreNoteResponse implements HttpNoContent
{
    public int $version = 1;
}
