<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractBoxResponse;

/**
 * Binds the param to `?NoteView`: the key is left out when null, as `?T` written there would be.
 *
 * @phpstan-import-type NoteView from ListNotesResponse
 *
 * @extends AbstractBoxResponse<?NoteView>
 */
final class BoxedNoteResponse extends AbstractBoxResponse implements HttpOk
{
}
