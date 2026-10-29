<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Shapes declared for others to take whole as their `_self`.
 *
 * @phpstan-type NoteInput = array{
 *     title: string,
 *     note?: string|null,
 * }
 * @phpstan-type FilterInput = array{
 *     product: string,
 * }
 * @phpstan-type MaybeNote = string|null
 * @phpstan-type LayeredNoteInput = ConfigBase & array{
 *     title: string,
 *     note?: MaybeNote,
 * }
 */
final class ReportShapes
{
}
