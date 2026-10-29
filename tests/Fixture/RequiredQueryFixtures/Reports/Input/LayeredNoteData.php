<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A body whose `_self` is a shape extending one only the project's config declares: what it takes
 * from it can't be read, and its own keys are read as they are declared all the same.
 *
 * @phpstan-import-type LayeredNoteInput from ReportShapes
 * @phpstan-type _self = LayeredNoteInput
 */
final class LayeredNoteData
{
    public ?string $title = null;

    public ?string $note = null;
}
