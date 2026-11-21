<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EndpointConstClashFixtures\Notes;

/**
 * Imports another module's ShowNote, which the module's own endpoint declares as its const.
 *
 * @phpstan-import-type ShowNote from \PTGS\TypeBridge\Tests\Fixture\EndpointConstClashFixtures\Shared\SharedShapes
 *
 * @phpstan-type _self = array{
 *     at: ShowNote,
 * }
 */
final class NoteView
{
}
