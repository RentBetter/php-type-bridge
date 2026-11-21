<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnresolvedImportSpelledFixtures\Common\View;

/**
 * Imports a `Money` from outside the source tree for its own code, and spells the word in a shape
 * key and a string literal — neither of which is the alias.
 *
 * @phpstan-import-type _self from \Vendor\Missing\Money as Money
 * @phpstan-type _self = array{
 *     Money: int,
 *     currency: 'Money',
 * }
 */
final class LedgerView {}
