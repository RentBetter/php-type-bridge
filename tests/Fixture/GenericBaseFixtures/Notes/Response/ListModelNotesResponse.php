<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractListResponse;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Model\Note;

/**
 * Binds the base's param to the class Note, which its file `use`s.
 *
 * @extends AbstractListResponse<Note>
 */
final class ListModelNotesResponse extends AbstractListResponse implements HttpOk
{
}
