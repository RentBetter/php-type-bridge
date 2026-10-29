<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSelfFixtures\Orders;

/**
 * Declares no `_self` of its own: it is its parent's, as its TypeScript is.
 */
final class ShippingAddress extends Address
{
}
