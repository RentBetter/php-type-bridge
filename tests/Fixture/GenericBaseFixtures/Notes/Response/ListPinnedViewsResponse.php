<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractListResponse;

/**
 * Binds the base's param to an intersection of its file's NoteView: read whole, where it is written.
 *
 * @phpstan-import-type NoteView from ListNotesResponse
 *
 * @extends AbstractListResponse<NoteView & array{pinned: bool}>
 */
final class ListPinnedViewsResponse extends AbstractListResponse implements HttpOk
{
}
