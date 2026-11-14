<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MutualInputFixtures\Pairs;

/**
 * @phpstan-type _self = array{
 *     a?: AData,
 *     y?: string,
 * }
 */
final class BData
{
    public ?string $a = null;

    public ?string $y = null;
}
