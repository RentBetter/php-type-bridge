<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryTraitNamedFixtures\Notes\View;

/**
 * The response's module has an `Item` of its own, which the trait never meant.
 *
 * @phpstan-type Item = array{noteId: int, body: string}
 */
final class NoteView {}
