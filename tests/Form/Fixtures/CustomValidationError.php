<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Form\Fixtures;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpUnprocessableEntity;

/**
 * App-specific validation-error envelope used to prove RequestFormProcessor throws
 * whatever the injected factory produces — here a richer shape with a top-level code.
 *
 * @phpstan-type _self = array{errorCode: string, failures: list<array{path: string, message: string}>}
 */
final class CustomValidationError extends ThrowableApiResponse implements HttpUnprocessableEntity
{
    /**
     * @param list<array{path: string, message: string}> $failures
     */
    public function __construct(
        public readonly array $failures,
        public readonly string $errorCode = 'CUSTOM_VALIDATION',
    ) {
        parent::__construct('Custom validation failed');
    }
}
