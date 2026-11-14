<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryTraitNamedFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryTraitLib\HasItems;

final class ListItemsResponse implements HttpOk
{
    use HasItems;

    public int $total = 0;
}
