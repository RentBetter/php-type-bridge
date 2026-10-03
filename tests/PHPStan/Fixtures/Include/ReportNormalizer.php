<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include;

use DateTimeImmutable;
use PTGS\TypeBridge\Normalizer\IncludeMarkers;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus;

/**
 * Declares its shapes itself, as `ShapeNormalizer<X, self>` would.
 *
 * @phpstan-type OtherData = array{id: string}
 * @phpstan-type CleanData = array{
 *     name: string,
 *     count?: int,
 *     tags: list<string>,
 *     at: array{tz: string, date: string},
 *     status: enum<CheckStatus>,
 *     owner?: ref<OtherData>,
 *     owners: list<ref<OtherData>>,
 *     debug: included<mixed>,
 *     trend?: included<list<int>>,
 * }
 * @phpstan-type RawEnumData = array{name: string, status: CheckStatus}
 * @phpstan-type NestedObjectData = array{rows: list<array{at?: DateTimeImmutable}>}
 */
abstract class ReportNormalizer
{
    use IncludeMarkers;
}
