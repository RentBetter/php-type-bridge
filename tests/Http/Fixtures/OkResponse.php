<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Fixtures;

use PTGS\TypeBridge\Contract\ApiSuccessResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * A 200 success response whose public properties form the JSON body.
 */
final readonly class OkResponse implements ApiSuccessResponse, HttpOk
{
    public function __construct(
        public string $id,
        public int $count,
    ) {}
}
