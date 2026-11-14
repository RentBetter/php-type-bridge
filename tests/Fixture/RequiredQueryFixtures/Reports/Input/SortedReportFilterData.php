<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A query extending an alias of an alias: `ListInput` is `PagedInput`, which requires `sort`.
 *
 * @phpstan-import-type ListInput from ReportShapes
 * @phpstan-type _self = ListInput & array{
 *     product?: string,
 * }
 */
final class SortedReportFilterData
{
    public ?string $sort = null;

    public ?string $product = null;
}
