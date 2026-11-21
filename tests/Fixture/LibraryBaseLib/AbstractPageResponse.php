<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's generic base: a page of what its child says.
 *
 * @template R
 */
abstract class AbstractPageResponse
{
    /** @var list<R> */
    public array $rows = [];
}
