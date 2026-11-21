<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractRowsResponse;

final class ListRowsResponse extends AbstractRowsResponse implements HttpOk
{
    public int $total = 0;
}
