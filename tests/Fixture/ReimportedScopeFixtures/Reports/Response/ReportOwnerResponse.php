<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Input\ReportScopes;

/**
 * Typed with `Owner` as ReportScopes re-imports it: OwnerData's shape, in the Common module.
 *
 * @phpstan-import-type Owner from ReportScopes
 */
final class ReportOwnerResponse implements HttpOk
{
    /**
     * @param Owner $owner
     */
    public function __construct(
        public readonly array $owner,
    ) {}
}
