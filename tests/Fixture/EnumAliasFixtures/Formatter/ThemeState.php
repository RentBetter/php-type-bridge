<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumAliasFixtures\Formatter;

/**
 * An enum with a shape of its own and another alias beside it.
 *
 * @phpstan-type ThemeStateId = 'none'|'info'|'danger'
 * @phpstan-type _self = array{state: ThemeStateId}
 */
enum ThemeState: string implements \JsonSerializable
{
    case NONE = 'none';
    case INFO = 'info';
    case DANGER = 'danger';

    /**
     * @return _self
     */
    public function jsonSerialize(): array
    {
        return ['state' => $this->value];
    }
}
