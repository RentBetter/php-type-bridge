<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryConfigAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractOwnerIdResponse;

final class ShowNoteResponse extends AbstractOwnerIdResponse implements HttpOk {}
