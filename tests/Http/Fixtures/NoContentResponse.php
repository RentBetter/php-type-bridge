<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Fixtures;

use PTGS\TypeBridge\Contract\ApiSuccessResponse;
use PTGS\TypeBridge\Status\HttpNoContent;

/**
 * A 204 success response — emits an empty body regardless of any properties.
 */
final readonly class NoContentResponse implements ApiSuccessResponse, HttpNoContent
{
    public function __construct(
        public string $ignored = 'should-not-be-serialized',
    ) {}
}
