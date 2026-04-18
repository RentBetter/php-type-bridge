<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class MissingDataClassResponse implements HttpOk
{
    public function __construct(
        public readonly string $status = 'ok',
    ) {}
}
