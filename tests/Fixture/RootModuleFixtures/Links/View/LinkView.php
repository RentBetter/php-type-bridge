<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootModuleFixtures\Links\View;

/**
 * A domain shape built on a shared one.
 *
 * @phpstan-import-type Other from \PTGS\TypeBridge\Tests\Fixture\RootModuleFixtures\Shared\View\OtherView
 *
 * @phpstan-type LinkData = array{other: Other}
 */
final class LinkView {}
