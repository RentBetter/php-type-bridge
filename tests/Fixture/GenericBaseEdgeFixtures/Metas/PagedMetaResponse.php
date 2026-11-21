<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Metas;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * Its meta extends what its subclass binds T to.
 *
 * @template T
 */
abstract class PagedMetaResponse implements ApiResponse, HttpOk
{
    /** @var T & array{total: int} */
    public array $meta = ['total' => 0];
}
