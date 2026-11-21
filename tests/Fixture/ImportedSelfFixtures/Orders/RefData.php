<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportedSelfFixtures\Orders;

/**
 * @phpstan-import-type Ref from Shapes as _self
 */
final class RefData
{
    public ?string $code = null;
}
