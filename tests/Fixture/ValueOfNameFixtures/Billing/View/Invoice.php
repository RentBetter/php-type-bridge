<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ValueOfNameFixtures\Billing\View;

use PTGS\TypeBridge\Tests\Fixture\ValueOfNameFixtures\Money\Enum\Currency;

/**
 * Names the enum both ways from another module: the object it serialises to, and its values.
 *
 * @phpstan-type _self = array{currency: Currency, code: value-of<Currency>}
 */
final class Invoice
{
}
