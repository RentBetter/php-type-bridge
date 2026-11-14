<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Requires a `note` its base leaves out, both typed with the config's MaybeText, which admits null:
 * as TypeScript reads `Base & {…}` it takes null on both sides, so a query may leave it out.
 *
 * @phpstan-import-type NoteBase from ReportShapes
 * @phpstan-type _self = NoteBase & array{
 *     note: MaybeText,
 * }
 */
final class ClearableNoteFilterData
{
    public ?string $note = null;
}
