<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Extends a shape it declares itself.
 *
 * @phpstan-type Scope = array{
 *     product: string,
 * }
 * @phpstan-type _self = Scope & array{
 *     month?: string,
 * }
 */
final class MonthlyReportFilterData
{
    public ?string $product = null;

    public ?string $month = null;
}
