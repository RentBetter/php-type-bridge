<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassSelfUnemittedFixtures\Notes\View;

/**
 * The Notes module's own `Money`, which the inherited property must not quietly become.
 *
 * @phpstan-type Money = array{cents: int}
 */
final class NoteView {}
