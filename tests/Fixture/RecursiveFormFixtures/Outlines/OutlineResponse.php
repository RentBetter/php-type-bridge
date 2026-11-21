<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines;

use PTGS\TypeBridge\Status\HttpCreated;

final class OutlineResponse implements HttpCreated
{
    public function __construct(
        public readonly string $id,
    ) {}
}
