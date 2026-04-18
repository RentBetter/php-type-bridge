<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedEndpointContract
{
    /**
     * @param list<CollectedApiResponseClass> $responses
     */
    public function __construct(
        public string $name,
        public string $domain,
        public string $controllerClass,
        public string $methodName,
        public array $responses,
        public ?CollectedEndpointRequest $request = null,
    ) {}
}
