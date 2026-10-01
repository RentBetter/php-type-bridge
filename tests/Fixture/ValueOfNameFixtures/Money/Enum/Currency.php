<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ValueOfNameFixtures\Money\Enum;

use PTGS\TypeBridge\Attribute\ValueOfName;

/**
 * An enum that serialises to an object, and gives its own name to that rather than its values.
 *
 * @phpstan-type _self = array{code: value-of<Currency>}
 */
#[ValueOfName('CurrencyCode')]
enum Currency: string implements \JsonSerializable
{
    case AUD = 'AUD';
    case NZD = 'NZD';

    /**
     * @return _self
     */
    public function jsonSerialize(): array
    {
        return ['code' => $this->value];
    }
}
