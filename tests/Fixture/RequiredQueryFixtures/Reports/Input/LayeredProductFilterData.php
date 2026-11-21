<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Extends a shape that itself extends one, the key declared at both levels: required at the root,
 * optional at the middle. An intersection holds every level, so it is required.
 *
 * @phpstan-type ProductRoot = array{
 *     product: string,
 * }
 * @phpstan-type ProductLayer = ProductRoot & array{
 *     product?: string,
 *     team?: string,
 * }
 * @phpstan-type _self = ProductLayer & array{
 *     q?: string,
 * }
 */
final class LayeredProductFilterData
{
    public ?string $product = null;
    public ?string $team = null;
    public ?string $q = null;
}
