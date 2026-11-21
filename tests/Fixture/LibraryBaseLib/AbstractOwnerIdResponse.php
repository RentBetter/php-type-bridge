<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's base whose property is typed by a project-wide alias, which PHPStan reads in any file.
 */
abstract class AbstractOwnerIdResponse
{
    /** @var UuidStr */
    public string $ownerId = '';
}
