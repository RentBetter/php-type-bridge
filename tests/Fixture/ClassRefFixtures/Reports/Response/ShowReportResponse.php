<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Reports\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Money\Value\Money;
use PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Money\Value\MoneyInterface;

final readonly class ShowReportResponse implements HttpOk
{
    /**
     * @param list<Money> $lines
     */
    public function __construct(
        public MoneyInterface $total,
        public array $lines,
    ) {}
}
