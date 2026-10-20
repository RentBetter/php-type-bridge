<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

/**
 * @phpstan-type _self = array{
 *     name: string,
 *     colour?: string,
 * }
 */
final class Label
{
    public string $name = '';

    public ?string $colour = null;
}
