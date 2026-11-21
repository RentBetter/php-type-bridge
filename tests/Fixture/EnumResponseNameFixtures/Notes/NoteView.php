<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumResponseNameFixtures\Notes;

/**
 * A key typed with the enum's class, named bare: the module's response of that name wins, as a
 * module's alias of it would, so the enum's values are named by nothing.
 *
 * @phpstan-type Note = array{state: Status}
 */
final class NoteView {}
