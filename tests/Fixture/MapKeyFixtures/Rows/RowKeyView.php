<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

use PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Keys\LooseView;

/**
 * Maps keyed by an alias: of a key no array has, of any key, of a key an array has; one imported
 * from another module, and an alias of that.
 *
 * @phpstan-import-type Loose from LooseView
 *
 * @phpstan-type MaybeId = int|null
 * @phpstan-type AnyKey = int|string
 * @phpstan-type Code = 'a'|'b'
 * @phpstan-type Anything = Loose
 * @phpstan-type RowKeys = array{
 *     byMaybe: array<MaybeId, string>,
 *     byAny: array<AnyKey, string>,
 *     byCode: array<Code, int>,
 *     byLoose: array<Loose, string>,
 *     byAnything: array<Anything, string>,
 * }
 */
final class RowKeyView {}
