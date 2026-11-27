<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Fixtures;

use PTGS\TypeBridge\Attribute\Metadata;
use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpConflict;

/**
 * A throwable 409 that says which request it answers: metadata, sent as `$trace`.
 */
final class TracedConflictError extends ThrowableApiResponse implements HttpConflict
{
    public function __construct(
        public readonly string $resource,
        #[Metadata]
        public readonly string $trace,
    ) {
        parent::__construct('Conflict');
    }
}
