<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Generics\Fixtures;

/**
 * @phpstan-type _self = array{label: string}
 */
final class WidgetData
{
    public ?string $label = null;
}
