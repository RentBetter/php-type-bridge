<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportedSelfFixtures\Orders;

/**
 * @phpstan-type _self = array{
 *     postal?: PostalData,
 *     ref?: RefData,
 * }
 */
final class OrderData
{
    public ?PostalData $postal = null;

    public ?RefData $ref = null;
}
