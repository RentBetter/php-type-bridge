<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportedSelfFixtures\Orders;

/**
 * @phpstan-import-type Postal from Shapes as _self
 */
final class PostalData
{
    public ?string $street = null;
}
