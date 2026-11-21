<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Items;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * TList's default names TItem, which its subclass binds.
 *
 * @template TItem
 * @template TList = list<TItem>
 */
abstract class PagedItemsResponse implements ApiResponse, HttpOk
{
    /** @var TList */
    public array $items = [];
}
