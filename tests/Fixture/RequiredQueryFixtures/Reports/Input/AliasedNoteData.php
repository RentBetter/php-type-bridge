<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A body whose `_self` is another class's shape, by name: its keys are that shape's.
 *
 * @phpstan-import-type NoteInput from ReportShapes
 * @phpstan-type _self = NoteInput
 */
final class AliasedNoteData
{
    public ?string $title = null;

    public ?string $note = null;
}
