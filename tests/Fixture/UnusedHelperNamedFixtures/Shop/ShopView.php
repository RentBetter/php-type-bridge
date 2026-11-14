<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnusedHelperNamedFixtures\Shop;

/**
 * Types of its own named as ones the include and expand helpers declare, in a module that has no
 * reference and no key sent only when asked for: it declares neither helper.
 *
 * @phpstan-type ListItem = array{name: string, qty: int}
 * @phpstan-type Ref = array{code: string}
 * @phpstan-type IncludePath = array{path: string}
 * @phpstan-type Basket = array{items: list<ListItem>, ref: Ref, path: IncludePath}
 */
final class ShopView {}
