<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Two levels of intersection: product from ProductScopeData, team from TeamScopeData.
 *
 * @phpstan-import-type _self from TeamScopeData as TeamScope
 * @phpstan-type _self = TeamScope & array{
 *     quarter?: string,
 * }
 */
final class QuarterlyReportFilterData
{
    public ?string $product = null;

    public ?string $team = null;

    public ?string $quarter = null;
}
