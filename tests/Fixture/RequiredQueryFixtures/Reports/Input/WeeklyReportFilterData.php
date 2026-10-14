<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Inherits its required `product` from the shape it extends, and adds an optional `week`.
 *
 * @phpstan-import-type _self from ProductScopeData as ProductScope
 * @phpstan-type _self = ProductScope & array{
 *     week?: string,
 * }
 */
final class WeeklyReportFilterData
{
    public ?string $product = null;

    public ?string $week = null;
}
