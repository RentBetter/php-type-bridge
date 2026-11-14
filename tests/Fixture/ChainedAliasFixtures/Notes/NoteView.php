<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ChainedAliasFixtures\Notes;

/**
 * A project-wide alias that names another: `NoteId` is `UuidStr`.
 *
 * @phpstan-type NoteData = array{id: NoteId}
 */
final class NoteView {}
