<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

/**
 * Bound to an alias of a key an array has: the key is the alias.
 *
 * @phpstan-import-type Code from RowKeyView
 *
 * @extends PagedRowsResponse<Code, string>
 */
final class CodeRowsResponse extends PagedRowsResponse
{
}
