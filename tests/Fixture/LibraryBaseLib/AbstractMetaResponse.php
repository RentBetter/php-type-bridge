<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's base response, outside the source tree read: its property is on the wire all the same.
 */
abstract class AbstractMetaResponse
{
    /** @var array{page: int} */
    public array $meta = ['page' => 1];
}
