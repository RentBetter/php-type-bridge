<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Valid;

/**
 * Imports another class's shape under a name that says what it is.
 *
 * @phpstan-import-type _self from CleanView as CleanData
 */
final class AliasedSelfImportNormalizer
{
    /**
     * @return list<CleanData>
     */
    public function normalizeAll(): array
    {
        return [];
    }
}
