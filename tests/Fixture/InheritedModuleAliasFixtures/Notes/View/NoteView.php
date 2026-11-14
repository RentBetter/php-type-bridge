<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedModuleAliasFixtures\Notes\View;

/**
 * The Notes module's own `Page`, which the inherited property must not become.
 *
 * @phpstan-type Page = array{
 *     cursor: string,
 * }
 */
final class NoteView {}
