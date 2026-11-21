<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedLibraryFixtures\Notes\Input;

use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\Item;

/**
 * Re-imports a library's `_self`, outside the source tree, for others to import from here.
 *
 * @phpstan-import-type _self from Item as LibraryItem
 */
final class NoteItems
{
}
