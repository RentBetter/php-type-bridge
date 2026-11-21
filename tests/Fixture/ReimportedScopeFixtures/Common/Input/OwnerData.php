<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Common\Input;

/**
 * @phpstan-type _self = array{
 *     owner: string,
 * }
 */
final class OwnerData
{
    public ?string $owner = null;
}
