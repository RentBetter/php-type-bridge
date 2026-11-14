<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnConstraintFixtures\Notes\Input;

/**
 * @phpstan-type _self = array{plain?: string, nested?: string, grouped?: string}
 */
final class OwnConstraintsData
{
    public ?string $plain = null;

    public ?string $nested = null;

    public ?string $grouped = null;
}
