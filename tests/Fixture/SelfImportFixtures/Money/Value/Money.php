<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SelfImportFixtures\Money\Value;

use PTGS\TypeBridge\Tests\Fixture\SelfImportFixtures\Http\Normalizer\MoneyNormalizer;

/**
 * Serialises to the shape the normaliser declares, so it imports it rather than restating it.
 *
 * @phpstan-import-type MoneyShape from MoneyNormalizer as _self
 */
final class Money implements MoneyLike, \JsonSerializable
{
    /**
     * @return _self
     */
    public function jsonSerialize(): array
    {
        return ['value' => '1.00', 'currency' => 'AUD'];
    }
}
