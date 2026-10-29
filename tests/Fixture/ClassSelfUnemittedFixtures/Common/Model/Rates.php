<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassSelfUnemittedFixtures\Common\Model;

use PTGS\TypeBridge\Attribute\PhpStanOnly;

/**
 * @phpstan-type _self = array{idr: int}
 */
#[PhpStanOnly]
final class Rates {}
