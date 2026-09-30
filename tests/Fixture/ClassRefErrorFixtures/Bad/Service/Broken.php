<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefErrorFixtures\Bad\Service;

/**
 * Names a class with no serialised shape, and is not marked PhpStanOnly.
 *
 * @phpstan-type Broken = array{at: \DateTimeImmutable}
 */
final class Broken {}
