<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

use PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Valid\CleanView;

/**
 * Imports `_self` under its own name, so `@return _self` below reads as this class's
 * own shape while meaning CleanView's.
 *
 * @phpstan-import-type _self from CleanView
 */
final class UnaliasedSelfImportNormalizer
{
    /**
     * @return list<_self>
     */
    public function normalizeAll(): array
    {
        return [];
    }
}
