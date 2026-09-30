<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Money\Value;

/**
 * An amount, with no shape of its own: it serialises to what MoneyInterface declares.
 *
 * @phpstan-import-type _self from MoneyInterface as MoneyData
 */
final class Money implements MoneyInterface
{
    /**
     * @return MoneyData
     */
    public function jsonSerialize(): array
    {
        return ['value' => '1.00', 'formatted' => '$1.00'];
    }
}
