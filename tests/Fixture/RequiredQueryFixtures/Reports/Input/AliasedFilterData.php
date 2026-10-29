<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A query whose `_self` is another class's shape, by name: its `product` can't be left out.
 *
 * @phpstan-import-type FilterInput from ReportShapes
 * @phpstan-type _self = FilterInput
 */
final class AliasedFilterData
{
    public ?string $product = null;
}
