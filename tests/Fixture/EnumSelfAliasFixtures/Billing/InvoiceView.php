<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumSelfAliasFixtures\Billing;

use PTGS\TypeBridge\Tests\Fixture\EnumSelfAliasFixtures\Notes\Status;

/**
 * A key typed with the enum's class: its shape.
 *
 * @phpstan-type InvoiceRow = array{status: Status}
 */
final class InvoiceView {}
