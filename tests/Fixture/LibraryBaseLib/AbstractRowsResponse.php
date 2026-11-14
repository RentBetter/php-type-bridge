<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's base whose property is typed with an alias the library declares itself.
 *
 * @phpstan-type Row = array{n: int}
 */
abstract class AbstractRowsResponse
{
    /** @var list<Row> */
    public array $rows = [];
}
