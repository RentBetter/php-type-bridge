<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RefEndpointFixtures\Notes;

/**
 * A reference, and a type named as one the expanding helpers declare.
 *
 * @phpstan-type ListItem = array{label: string}
 * @phpstan-type AuthorData = array{id: string}
 * @phpstan-type NoteData = array{author: ref<AuthorData>}
 */
final class NoteView {}
