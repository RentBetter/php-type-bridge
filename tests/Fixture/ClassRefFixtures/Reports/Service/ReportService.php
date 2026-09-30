<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Reports\Service;

use PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Catalog\Enum\TaskStatus;
use PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Money\Value\Money;
use PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Money\Value\MoneyInterface;
use PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Reports\View\Summary;

/**
 * A report as PHPStan sees it — holding the objects — which json_encode turns into their JSON.
 *
 * @phpstan-type Summary = array{count: int}
 * @phpstan-type ExpenseItem = array{
 *     total: MoneyInterface,
 *     subTotal: Money,
 *     net: \PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Money\Value\MoneyInterface,
 *     status: TaskStatus,
 *     summary: Summary,
 * }
 */
final class ReportService {}
