<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\DeepAliasFixtures\Admin\Notes;

use PTGS\TypeBridge\Tests\Fixture\DeepAliasFixtures\Admin\Checks\CheckShapes;

/**
 * A `Meta` of its own beside the `Admin/Checks` module's, imported under another name.
 *
 * @phpstan-import-type Meta from CheckShapes as CheckMeta
 *
 * @phpstan-type Meta = array{n: int}
 * @phpstan-type Uses = array{mine: Meta, theirs: CheckMeta}
 */
final class NoteShapes
{
}
