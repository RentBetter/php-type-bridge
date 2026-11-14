<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MutualInputFixtures\Pairs;

/**
 * @phpstan-type _self = array{
 *     b?: BData,
 *     x?: string,
 * }
 */
final class AData
{
    public ?string $b = null;

    public ?string $x = null;
}
