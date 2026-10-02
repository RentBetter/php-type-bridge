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
        public ?CollectedMcpTool $mcp = null,
        public string $httpMethod = 'GET',
        public string $httpPath = '',
    ) {}

    /**
     * Whether a success response sends a body: what the include query parameters shape.
     */
    public function hasSuccessBody(): bool
    {
        foreach ($this->responses as $response) {
            if (!$response->error && 204 !== $response->status) {
                return true;
            }
        }

        return false;
    }
}
