<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Input;

/**
 * Extends `Scope` as ReportScopes re-imports it: ProductScopeData's shape, not ReportScopes' own.
 *
 * @phpstan-import-type Scope from ReportScopes
 * @phpstan-type _self = Scope & array{
 *     period?: string,
 * }
 */
final class ScopedReportFilterData
{
    public ?string $product = null;

    public ?string $period = null;
}
