<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractListResponse;

/**
 * Its items are its own file's NoteView; its labels are left to the bound.
 *
 * @phpstan-type NoteView = array{title: string}
 *
 * @extends AbstractListResponse<NoteView>
 */
final class ListNotesResponse extends AbstractListResponse implements HttpOk
{
}
