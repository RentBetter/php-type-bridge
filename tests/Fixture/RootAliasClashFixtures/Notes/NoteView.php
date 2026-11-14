<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootAliasClashFixtures\Notes;

/**
 * A reference, so the expanding helpers are declared.
 *
 * @phpstan-type AuthorData = array{id: string}
 * @phpstan-type NoteData = array{author: ref<AuthorData>}
 */
final class NoteView {}
