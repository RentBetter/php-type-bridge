<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ConfigAliasImportFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\ConfigAliasImportFixtures\Calendar\CalendarTypes;

/**
 * Calendar's `Date` imported beside a project-wide alias whose TypeScript names TypeScript's.
 *
 * @phpstan-import-type Date from CalendarTypes
 * @phpstan-type Note = array{at: Stamp, on: Date}
 */
final class NoteView {}
