<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedEndpointRequest
{
    public function __construct(
        public ?CollectedInputReference $query = null,
        public ?CollectedInputReference $body = null,
        public ?CollectedInputReference $path = null,
    ) {}

    public function hasAnyInput(): bool
    {
        return null !== $this->query || null !== $this->body || null !== $this->path;
    }
}
