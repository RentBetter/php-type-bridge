<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Fixtures;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpNotFound;

/**
 * A throwable 404 error response whose public properties form the JSON body.
 */
final class NotFoundError extends ThrowableApiResponse implements HttpNotFound
{
    public function __construct(
        public readonly string $resource,
    ) {
        parent::__construct('Not found');
    }
}
