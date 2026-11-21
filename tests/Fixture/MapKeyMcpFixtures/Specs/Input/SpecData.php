<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyMcpFixtures\Specs\Input;

/**
 * A map keyed by an alias of this file's: the tool reads it as the alias is declared.
 *
 * @phpstan-type Code = 'a'|'b'
 * @phpstan-type _self = array{name: string, byCode: array<Code, int>}
 */
final class SpecData
{
    public ?string $name = null;

    /** @var array<Code, int> */
    public array $byCode = [];
}
