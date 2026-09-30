<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Reports\View;

/**
 * Names `Summary` from the same namespace as the Summary class — and the domain's Summary
 * alias is still what it means.
 *
 * @phpstan-type Totals = array{summary: Summary}
 */
final class Totals {}
