<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Extends a shape that takes more keys than it lists: the query still requires the base's
 * `product`, as a string.
 *
 * @phpstan-type OpenScope = array{
 *     region: string|null,
 *     product: string,
 *     ...
 * }
 * @phpstan-type _self = OpenScope & array{
 *     page?: int,
 * }
 */
final class OpenRegionFilterData
{
    public ?string $region = null;
    public string $product = '';
    public ?int $page = null;
}
