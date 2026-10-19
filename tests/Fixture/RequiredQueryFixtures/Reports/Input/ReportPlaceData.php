<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Embeds an address typed with the alias it imports — another class's `_self` — as such a key
 * usually is. Its keys are what that shape declares, read through the alias.
 *
 * @phpstan-import-type _self from ReportAddressInput as ReportAddress
 * @phpstan-type _self = array{
 *     address?: ReportAddress,
 * }
 */
final class ReportPlaceData
{
    public ?ReportAddressInput $address = null;
}
