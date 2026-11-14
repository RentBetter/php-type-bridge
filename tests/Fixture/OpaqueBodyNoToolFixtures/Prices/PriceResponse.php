<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueBodyNoToolFixtures\Prices;

use PTGS\TypeBridge\Status\HttpOk;

final class PriceResponse implements HttpOk
{
    public string $id = '';
}
