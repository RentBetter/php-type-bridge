<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedEndpointRequest
{
    /**
     * @param list<CollectedPathParam>|null $pathParams the route's placeholders, null where it has
     *        none: a tool's path arguments, and the Endpoint's path where no #[ApiRequest(path:)]
     *        class ($path) types it
     */
    public function __construct(
        public ?CollectedInputReference $query = null,
        public ?CollectedInputReference $body = null,
        public ?CollectedInputReference $path = null,
        public ?array $pathParams = null,
    ) {}

    public function hasAnyInput(): bool
    {
        return null !== $this->query || null !== $this->body || null !== $this->path || null !== $this->pathParams;
    }
}
