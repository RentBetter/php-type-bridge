<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseNamedFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractItemsResponse;

final class ListItemsResponse extends AbstractItemsResponse implements HttpOk
{
    public int $total = 0;
}
