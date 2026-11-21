<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryEnumFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractStatusResponse;

final class ShowNoteResponse extends AbstractStatusResponse implements HttpOk {}
