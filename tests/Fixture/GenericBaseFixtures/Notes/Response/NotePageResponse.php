<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractPageResponse;

/**
 * @extends AbstractPageResponse<int>
 */
final class NotePageResponse extends AbstractPageResponse implements HttpOk
{
}
