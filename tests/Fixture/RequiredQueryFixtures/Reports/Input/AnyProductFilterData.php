<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A key whose value may be null: a query string carries no null, so the only way to send one is to
 * leave the key out — which a caller may.
 *
 * @phpstan-type _self = array{
 *     product: string|null,
 * }
 */
final class AnyProductFilterData
{
    public ?string $product = null;
}
