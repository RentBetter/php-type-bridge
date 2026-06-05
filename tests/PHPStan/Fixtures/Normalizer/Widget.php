<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer;

final class Widget
{
    public function __construct(public int $id = 0) {}
}
