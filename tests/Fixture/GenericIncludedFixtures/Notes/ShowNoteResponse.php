<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericIncludedFixtures\Notes;

/**
 * Its envelope's data bound to an included key extending a shape: sent only when asked for.
 *
 * @phpstan-import-type NoteCore from NoteView
 *
 * @extends AbstractEnvelope<included<NoteCore & array{extra: int}>>
 */
final class ShowNoteResponse extends AbstractEnvelope
{
}
