<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A report is one product's: `product` can't be left out, `week` can.
 *
 * @phpstan-type _self = array{
 *     product: string,
 *     week?: string,
 * }
 */
final class ReportFilterData
{
    public ?string $product = null;

    public ?string $week = null;
}
