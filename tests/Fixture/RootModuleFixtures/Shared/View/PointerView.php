<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootModuleFixtures\Shared\View;

/**
 * A shared shape built on a domain one.
 *
 * @phpstan-import-type LinkData from \PTGS\TypeBridge\Tests\Fixture\RootModuleFixtures\Links\View\LinkView
 *
 * @phpstan-type Pointer = array{link: LinkData}
 */
final class PointerView {}
