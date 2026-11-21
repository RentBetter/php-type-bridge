<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GlobalImportedFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\GlobalImportedFixtures\Common\Record;

/**
 * A type named as TypeScript's global `Record`, imported into a module whose code writes a map.
 *
 * @phpstan-type NoteData = array{record: Record, tags: array<string, string>}
 */
final class NoteView {}
