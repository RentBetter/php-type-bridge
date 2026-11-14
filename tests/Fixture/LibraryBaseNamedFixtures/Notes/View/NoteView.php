<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseNamedFixtures\Notes\View;

/**
 * The response's module has an `Item` of its own, which the library never meant.
 *
 * @phpstan-type Item = array{noteId: int, body: string}
 */
final class NoteView {}
