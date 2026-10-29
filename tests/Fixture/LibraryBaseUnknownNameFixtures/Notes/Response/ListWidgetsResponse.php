<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseUnknownNameFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractWidgetsResponse;

final class ListWidgetsResponse extends AbstractWidgetsResponse implements HttpOk
{
    public int $total = 0;
}
