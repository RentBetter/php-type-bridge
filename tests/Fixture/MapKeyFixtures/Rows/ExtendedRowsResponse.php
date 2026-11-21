<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

/**
 * Bound to an intersection, read whole where it is written: its map is keyed by an alias of this
 * file's.
 *
 * @phpstan-import-type MaybeId from RowKeyView
 * @phpstan-import-type RowKeys from RowKeyView
 *
 * @extends PagedRowsResponse<int, RowKeys & array{extra: array<MaybeId, string>}>
 */
final class ExtendedRowsResponse extends PagedRowsResponse
{
}
