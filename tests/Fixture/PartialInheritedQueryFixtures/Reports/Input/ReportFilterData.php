<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PartialInheritedQueryFixtures\Reports\Input;

/**
 * @phpstan-import-type _self from ScopeData as Scope
 * @phpstan-type _self = Scope & array{
 *     month?: string,
 * }
 */
final class ReportFilterData
{
    public ?string $region = null;
    public ?string $month = null;
}
