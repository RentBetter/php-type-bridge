<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyUsedFixtures\Notes\Response;

/**
 * A class that happens to share the PHPStan-only alias's name: it must not stand in for it.
 *
 * @phpstan-type _self = array{label: string}
 */
final class Row {}
