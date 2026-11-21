<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFanOutFixtures\Stock;

/**
 * Names itself: what its children are is what it is.
 *
 * @phpstan-type _self = array{
 *     name?: string,
 *     children?: list<CategoryData>,
 * }
 */
final class CategoryData
{
}
