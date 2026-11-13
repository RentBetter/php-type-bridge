<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFanOutFixtures\Stock;

/**
 * Holds a category, which names itself: read once, a rack is too.
 *
 * @phpstan-type _self = array{
 *     category?: CategoryData,
 * }
 */
final class RackData
{
}
