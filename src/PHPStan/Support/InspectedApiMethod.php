<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Support;

final readonly class InspectedApiMethod
{
    /**
     * @param list<string> $httpMethods
     * @param list<class-string> $declaredResponses
     */
    public function __construct(
        public ?string $path,
        public array $httpMethods,
        public bool $hasApiRequest,
        public bool $hasApiResponses,
        public array $declaredResponses,
    ) {}

    public function isApiRoute(): bool
    {
        return null !== $this->path && str_starts_with($this->path, '/api');
    }

    public function isMutatingRoute(): bool
    {
        foreach ($this->httpMethods as $method) {
            if (\in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                return true;
            }
        }

        return false;
    }
}
