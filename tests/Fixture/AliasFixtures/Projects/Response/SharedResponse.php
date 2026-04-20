<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AliasFixtures\Projects\Response;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpNotFound;

final class SharedResponse extends ThrowableApiResponse implements HttpNotFound
{
    public function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('Not found');
    }
}
