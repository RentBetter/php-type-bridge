<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedClassNameFixtures\Notes\View;

/**
 * Declares an alias called Money in the Notes module, which is not the class.
 *
 * @phpstan-type Money = array{
 *     note: string,
 * }
 */
final class NoteView {}
