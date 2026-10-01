<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final class CollectedDomain
{
    /** @var array<string, CollectedType> */
    public array $types = [];

    /**
     * Classes that declare what they serialise to by importing it — `@phpstan-import-type
     * MoneyData from AbstractV2Normalizer as _self` — keyed by class: the JSON is that type.
     *
     * @var array<string, ImportedType>
     */
    public array $selfImports = [];

    public function __construct(
        public readonly string $name,
    ) {}
}
