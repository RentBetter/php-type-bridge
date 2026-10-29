<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportedAliasNameFixtures\Lists;

use PTGS\TypeBridge\Status\HttpOk;

final class ListResponse implements HttpOk
{
    public function __construct(
        public readonly int $total,
    ) {}
}
