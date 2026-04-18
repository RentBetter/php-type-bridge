<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpUnprocessableEntity;

final class ValidationErrorResponse extends ThrowableApiResponse implements HttpUnprocessableEntity
{
    /**
     * @param list<array{path: string, message: string}> $errors
     */
    public function __construct(
        /** @var list<array{path: string, message: string}> */
        public readonly array $errors,
    ) {
        parent::__construct('Validation failed');
    }
}
