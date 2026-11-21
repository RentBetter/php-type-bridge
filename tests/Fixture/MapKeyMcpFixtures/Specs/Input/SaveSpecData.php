<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyMcpFixtures\Specs\Input;

/**
 * @phpstan-type _self = array{spec?: SpecData}
 */
final class SaveSpecData
{
    public ?SpecData $spec = null;
}
