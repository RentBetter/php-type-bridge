<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiRequest
{
    /**
     * @param class-string|null $query
     * @param class-string|null $body
     * @param class-string<object>|null $path
     */
    public function __construct(
        public ?string $query = null,
        public ?string $body = null,
        public ?string $path = null,
    ) {}
}
