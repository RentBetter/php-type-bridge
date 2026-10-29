<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Elsewhere\Notes;

/**
 * Its namespace is not where its file is, so the autoloader can't find it: its types are still the
 * file's, and still emitted.
 *
 * @phpstan-type NoteFilter = array{
 *     status: string,
 * }
 */
final class NoteShapes
{
}
