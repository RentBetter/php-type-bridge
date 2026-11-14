<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's base whose property is typed by the library's own enum, as its file names it.
 */
abstract class AbstractStatusResponse
{
    /** @var value-of<Status> */
    public string $status = 'live';
}
