<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QualifiedClassFixtures\Notes;

/**
 * An alias of this module's with the short name of a class elsewhere: a name written in full
 * is the class, whatever alias shares its short name.
 *
 * @phpstan-type Money = array{cents: int}
 * @phpstan-type NoteTotals = array{total: \PTGS\TypeBridge\Tests\Fixture\QualifiedClassFixtures\Common\Money, own: Money}
 */
final class NoteView {}
