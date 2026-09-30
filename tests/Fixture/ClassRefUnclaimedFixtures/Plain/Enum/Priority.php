<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefUnclaimedFixtures\Plain\Enum;

/**
 * An enum no type-publishing emitter claims, with an alias of its own — so the built-in
 * `value-of` and `_self` conventions both claim it, at equal priority.
 *
 * @phpstan-type PriorityId = 'low'|'high'
 */
enum Priority: string
{
    case LOW = 'low';
    case HIGH = 'high';
}
