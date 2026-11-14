<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

/**
 * Bound to a key no array has: PHP keys a null as ''.
 *
 * @extends PagedRowsResponse<int|null, string>
 */
final class NullableRowsResponse extends PagedRowsResponse
{
}
