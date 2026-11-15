<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ConfigAliasShadowFixtures\Notes;

/**
 * A module's own `Date`, beside a project-wide alias whose TypeScript names TypeScript's.
 *
 * @phpstan-type Date = string
 * @phpstan-type Note = array{at: Stamp, on: Date}
 */
final class NoteView {}
