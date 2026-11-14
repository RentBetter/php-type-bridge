<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

use PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Keys\KeyedBaseResponse;

/** Inherits a map keyed by an alias of the base's module, which this one has none of. */
final class KeyedRowsResponse extends KeyedBaseResponse
{
}
