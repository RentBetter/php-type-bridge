<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * @extends AbstractNoteListResponse<int>
 */
final class ListPinnedNotesResponse extends AbstractNoteListResponse implements HttpOk
{
}
