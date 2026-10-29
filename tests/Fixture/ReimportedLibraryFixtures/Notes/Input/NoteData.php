<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedLibraryFixtures\Notes\Input;

/**
 * Typed with `LibraryItem` as NoteItems re-imports it: from outside the source tree, never emitted.
 *
 * @phpstan-import-type LibraryItem from NoteItems
 * @phpstan-type _self = array{
 *     item: LibraryItem,
 * }
 */
final class NoteData
{
}
