<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

use PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Keys\LooseView;

/**
 * Maps keyed by an alias: of a key no array has, of any key, of a key an array has; one imported
 * from another module, an alias of that, a union naming one, and an alias of any key.
 *
 * @phpstan-import-type Loose from LooseView
 *
 * @phpstan-type MaybeId = int|null
 * @phpstan-type AnyKey = int|string
 * @phpstan-type Code = 'a'|'b'
 * @phpstan-type Anything = Loose
 * @phpstan-type MaybeOrX = MaybeId|'x'
 * @phpstan-type ArrayKey = array-key
 * @phpstan-type RowKeys = array{
 *     byMaybe: array<MaybeId, string>,
 *     byAny: array<AnyKey, string>,
 *     byCode: array<Code, int>,
 *     byLoose: array<Loose, string>,
 *     byAnything: array<Anything, string>,
 *     byMaybeOrX: array<MaybeOrX, string>,
 *     byArrayKey: array<ArrayKey, string>,
 * }
 */
final class RowKeyView {}
