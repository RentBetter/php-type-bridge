<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootModuleFixtures\Notes\View;

/**
 * Imports an alias no shape here uses — for its own code, say — and uses a config alias and an
 * include generic.
 *
 * @phpstan-import-type Other from \PTGS\TypeBridge\Tests\Fixture\RootModuleFixtures\Shared\View\OtherView
 *
 * @phpstan-type _self = array{id: UuidStr, body: included<string>}
 */
final class NoteView {}
