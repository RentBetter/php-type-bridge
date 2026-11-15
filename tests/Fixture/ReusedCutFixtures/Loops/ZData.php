<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReusedCutFixtures\Loops;

/**
 * Reads Y — cut short where it names Z — and then X, which reads that Y back.
 *
 * @phpstan-type _self = array{
 *     y?: YData,
 *     x?: XData,
 * }
 */
final class ZData
{
}
