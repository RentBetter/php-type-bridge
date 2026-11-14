<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseOwnedFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractOwnedResponse;

final class ShowNoteResponse extends AbstractOwnedResponse implements HttpOk
{
    public string $id = '';
}
