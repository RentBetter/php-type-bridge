<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AliasFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class SharedResponse implements HttpOk
{
    public function __construct(
        public readonly string $message,
    ) {}
}
