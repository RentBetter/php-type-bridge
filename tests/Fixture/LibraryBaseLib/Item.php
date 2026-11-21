<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's class with a shape of its own, outside the source tree: nothing emits it.
 *
 * @phpstan-type _self = array{sku: string}
 */
final class Item {}
