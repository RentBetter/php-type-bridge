<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassSelfUnemittedFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\ClassSelfUnemittedFixtures\Common\Model\Money;

abstract class AbstractPricedResponse implements HttpOk
{
    public function __construct(
        public readonly Money $price,
    ) {}
}
