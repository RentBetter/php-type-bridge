<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseAliasFixtures\Notes\View;

/**
 * The response's module has a `Row` of its own, which the library never meant.
 *
 * @phpstan-type Row = array{other: string}
 */
final class RowView {}
