<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * @phpstan-type _self = array{
 *     product?: string,
 * }
 */
final class ReportListFilterData
{
    public ?string $product = null;
}
