<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumSelfAliasFixtures\Notes;

/**
 * @phpstan-type Status = 'draft'|'final'
 * @phpstan-type Note = array{state: Status}
 */
final class NoteView {}
