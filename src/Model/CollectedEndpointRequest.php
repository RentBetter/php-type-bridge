<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedEndpointRequest
{
    /**
     * @param list<CollectedPathParam>|null $pathParams Route-derived path params, set when the
     *        path comes from the #[Route] placeholders rather than an explicit #[ApiRequest(path:)]
     *        class. Mutually exclusive with $path.
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
