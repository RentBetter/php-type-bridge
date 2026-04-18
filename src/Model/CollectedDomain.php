<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final class CollectedDomain
{
    /** @var array<string, CollectedType> */
    public array $types = [];

    public function __construct(
        public readonly string $name,
    ) {}
}
