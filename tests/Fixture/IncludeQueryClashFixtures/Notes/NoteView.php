<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IncludeQueryClashFixtures\Notes;

/**
 * Imports another module's ShowNoteQuery, which the module's own endpoint declares for its
 * `?include=` alone.
 *
 * @phpstan-import-type ShowNoteQuery from \PTGS\TypeBridge\Tests\Fixture\IncludeQueryClashFixtures\Shared\SharedShapes
 *
 * @phpstan-type _self = array{
 *     q: ShowNoteQuery,
 * }
 */
final class NoteView
{
}
