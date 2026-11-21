<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReusedCutFixtures\Loops;

/**
 * Reads Z, then X: X is read once where Z is being read, and again where it is not.
 *
 * @phpstan-type _self = array{
 *     z?: ZData,
 *     x?: XData,
 * }
 */
final class InputData
{
}
