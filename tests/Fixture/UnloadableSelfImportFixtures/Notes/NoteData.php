<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnloadableSelfImportFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\UnloadableSelfImportFixtures\DevOnly\DevOnlyNote;

/**
 * @phpstan-import-type _self from DevOnlyNote as DevNote
 * @phpstan-type Uses = array{note: DevNote}
 */
final class NoteData
{
}
