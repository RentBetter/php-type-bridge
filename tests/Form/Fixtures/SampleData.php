<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Form\Fixtures;

/**
 * @phpstan-type _self = array{name: string, count: int}
 */
final class SampleData
{
    public ?string $name = null;
    public ?int $count = null;
}
