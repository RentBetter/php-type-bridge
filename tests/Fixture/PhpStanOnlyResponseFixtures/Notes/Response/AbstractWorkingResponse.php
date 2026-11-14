<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * @phpstan-type Working = array{at: \DateTimeImmutable}
 */
#[PhpStanOnly(['Working'])]
abstract class AbstractWorkingResponse implements HttpOk
{
    public function __construct(
        public readonly string $title,
    ) {}
}
