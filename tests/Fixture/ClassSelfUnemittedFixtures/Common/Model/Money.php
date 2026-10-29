<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassSelfUnemittedFixtures\Common\Model;

/**
 * Its JSON is Rates' `_self` — which is PHPStan-only, so never emitted: a misconfiguration to name.
 *
 * @phpstan-import-type _self from Rates as _self
 */
final class Money {}
