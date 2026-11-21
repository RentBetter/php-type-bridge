<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnusedHelperNamedFixtures\Notes;

/**
 * A module whose one key sent only when asked for is an intersection's.
 *
 * @phpstan-type NoteBase = array{id: string}
 * @phpstan-type NoteData = NoteBase & array{debug: included<string>}
 */
final class NoteView {}
