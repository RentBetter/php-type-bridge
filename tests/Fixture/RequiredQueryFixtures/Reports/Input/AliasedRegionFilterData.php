<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A key typed with an alias that admits no null: still a key a caller must send.
 *
 * @phpstan-type Region = 'north'|'south'
 * @phpstan-type _self = array{
 *     region: Region,
 * }
 */
final class AliasedRegionFilterData
{
    public ?string $region = null;
}
