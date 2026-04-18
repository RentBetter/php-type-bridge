<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;
use PTGS\TypeBridge\Contract\ApiResponse;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiResponses
{
    /**
     * @param list<class-string<ApiResponse>> $responses
     */
    public function __construct(
        public array $responses,
    ) {}
}
