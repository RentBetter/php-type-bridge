<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnClassAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;

/** A base in the same module, naming what an own property of the module would. */
abstract class AbstractSummaryResponse implements HttpOk
{
    public function __construct(
        /** @var Summary */
        public readonly array $summary,
    ) {}
}
