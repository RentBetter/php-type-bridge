<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Input;

use PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Common\Input\OwnerData;

/**
 * Re-imports `Scope`, and `Owner` from another module, for others to import from here, and declares
 * a shape of its own beside them.
 *
 * @phpstan-import-type _self from ProductScopeData as Scope
 * @phpstan-import-type _self from OwnerData as Owner
 * @phpstan-type _self = array{
 *     owner: string,
 * }
 */
final class ReportScopes
{
    public ?string $owner = null;
}
