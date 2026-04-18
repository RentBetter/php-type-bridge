<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;
use PTGS\TypeBridge\Contract\ContractFormType;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiRequest
{
    /**
     * @param class-string<ContractFormType>|null $query
     * @param class-string<ContractFormType>|null $body
     * @param class-string|null $path
     */
    public function __construct(
        public ?string $query = null,
        public ?string $body = null,
        public ?string $path = null,
    ) {}
}
