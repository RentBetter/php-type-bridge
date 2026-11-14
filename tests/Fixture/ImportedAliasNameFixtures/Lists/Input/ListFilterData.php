<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportedAliasNameFixtures\Lists\Input;

/**
 * Shapes' `Filter` imported as `Code`, which Shapes declares too: its `code: Code` is Shapes' own.
 *
 * @phpstan-import-type Filter from Shapes as Code
 * @phpstan-type _self = array{filter?: Code}
 */
final class ListFilterData
{
    /** @var array<string, mixed>|null */
    public ?array $filter = null;
}
