<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Embeds an address typed with the alias it imports — another class's `_self` — as such a key
 * usually is, and a billing address extending it. Their keys are what those shapes declare, read
 * through the aliases, the billing one's its base's and its own.
 *
 * @phpstan-import-type _self from ReportAddressInput as ReportAddress
 * @phpstan-type ReportBilling = ReportAddress & array{
 *     postcode: string,
 * }
 * @phpstan-type _self = array{
 *     address?: ReportAddress,
 *     billing?: ReportBilling,
 * }
 */
final class ReportPlaceData
{
    public ?ReportAddressInput $address = null;
    public ?ReportBillingInput $billing = null;
}
