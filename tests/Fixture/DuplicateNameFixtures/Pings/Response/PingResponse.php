<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\DuplicateNameFixtures\Pings\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class PingResponse implements HttpOk
{
    public function __construct(
        public readonly string $message,
    ) {}
}
