<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnClassAliasFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

abstract class AbstractDigestResponse implements HttpOk
{
    public function __construct(
        /** @var Digest */
        public readonly array $digest,
    ) {}
}
