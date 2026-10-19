<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConcreteClassNameFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\InheritedConcreteClassNameFixtures\Billing\Model\Money;

/** A response of its own and the base of another: its `price` is one type whichever is sent. */
class PriceResponse implements HttpOk
{
    public function __construct(
        /** @var Money */
        public readonly array $price,
    ) {}
}
