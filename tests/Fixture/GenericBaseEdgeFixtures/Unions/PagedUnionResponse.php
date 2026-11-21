<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Unions;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * Its meta extends what its subclass binds T to: a union, which the extension holds to whole.
 *
 * @template T
 */
abstract class PagedUnionResponse implements ApiResponse, HttpOk
{
    /** @var T & array{total: int} */
    public array $meta = ['total' => 0];
}
