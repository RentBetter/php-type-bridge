<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Input;

/**
 * Re-imports `Scope` for others to import from here, and declares a shape of its own beside it.
 *
 * @phpstan-import-type _self from ProductScopeData as Scope
 * @phpstan-type _self = array{
 *     owner: string,
 * }
 */
final class ReportScopes
{
    public ?string $owner = null;
}
