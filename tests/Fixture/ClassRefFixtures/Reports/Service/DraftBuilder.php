<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Reports\Service;

use PTGS\TypeBridge\Attribute\PhpStanOnly;

/**
 * Working data that is never serialised.
 *
 * @phpstan-type Draft = array{at: \DateTimeImmutable, total: \stdClass}
 */
#[PhpStanOnly]
final class DraftBuilder {}
