<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

/**
 * Bound to an alias of a key no array has.
 *
 * @phpstan-import-type MaybeId from RowKeyView
 *
 * @extends PagedRowsResponse<MaybeId, string>
 */
final class AliasRowsResponse extends PagedRowsResponse
{
}
