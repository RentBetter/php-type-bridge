<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Deprecated204ImportFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\Deprecated204ImportFixtures\Common\Input\NoteCodes;

/**
 * Typed with another module's alias named as the 204 beside it keeps its old name: imported under
 * another name, as any import a name of the module's own would shadow.
 *
 * @phpstan-import-type IRestoreNoteResponse from NoteCodes
 */
final class ShowNoteResponse implements HttpOk
{
    /** @var IRestoreNoteResponse */
    public string $code = '';
}
