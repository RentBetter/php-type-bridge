<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * An intersection itself: a product's scope, and a team in it.
 *
 * @phpstan-import-type _self from ProductScopeData as ProductScope
 * @phpstan-type _self = ProductScope & array{
 *     team: string,
 * }
 */
final class TeamScopeData
{
    public ?string $product = null;

    public ?string $team = null;
}
