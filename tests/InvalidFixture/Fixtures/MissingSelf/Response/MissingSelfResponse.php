<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingSelf\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class MissingSelfResponse implements HttpOk
{
    public function __construct(
        public readonly string $status = 'ok',
    ) {}
}
