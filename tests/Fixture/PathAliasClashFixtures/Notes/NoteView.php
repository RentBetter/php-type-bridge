<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PathAliasClashFixtures\Notes;

/**
 * Imports another module's GetNotePathParams, which the module's own endpoint also declares.
 *
 * @phpstan-import-type GetNotePathParams from \PTGS\TypeBridge\Tests\Fixture\PathAliasClashFixtures\Shared\SharedShapes
 *
 * @phpstan-type _self = array{
 *     at: GetNotePathParams,
 * }
 */
final class NoteView
{
}
