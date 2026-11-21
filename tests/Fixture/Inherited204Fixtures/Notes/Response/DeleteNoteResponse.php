<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Inherited204Fixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpNoContent;

/** A 204 sends no body, whatever properties it inherits. */
final class DeleteNoteResponse extends AbstractVersionedResponse implements HttpNoContent
{
}
