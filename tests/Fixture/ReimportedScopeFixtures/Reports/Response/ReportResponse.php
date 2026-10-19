<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class ReportResponse implements HttpOk
{
    public function __construct(
        public readonly int $total,
    ) {}
}
