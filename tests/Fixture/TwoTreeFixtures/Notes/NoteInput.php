<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TwoTreeFixtures\Notes;

/**
 * Its Charge's Fee is read where the tree holds FeeShapes, and stays a name where it doesn't.
 *
 * @phpstan-import-type Fee from \PTGS\TypeBridge\Tests\Fixture\TwoTreeFixtures\Shared\FeeShapes
 *
 * @phpstan-type Charge = array{
 *     fee?: Fee,
 * }
 * @phpstan-type _self = array{
 *     charge?: Charge,
 * }
 */
final class NoteInput
{
}
