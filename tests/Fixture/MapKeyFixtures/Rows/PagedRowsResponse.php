<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Rows;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * Rows keyed by what a subclass binds TKey to.
 *
 * @template TKey
 * @template TValue
 */
abstract class PagedRowsResponse implements ApiResponse, HttpOk
{
    /** @var array<TKey, TValue> */
    public array $rows = [];
}
