<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IncludePathNamedTypeFixtures\Notes;

/**
 * A type of its own named `IncludePath`, as one the including helpers declare, beside a key sent
 * only when asked for.
 *
 * @phpstan-type IncludePath = array{path: string}
 * @phpstan-type NoteData = array{debug: included<string>, path: IncludePath}
 */
final class NoteView {}
