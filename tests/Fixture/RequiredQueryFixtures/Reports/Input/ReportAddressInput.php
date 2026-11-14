<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * An address an update may change a part of, or clear its street.
 *
 * @phpstan-type _self = array{
 *     street?: string|null,
 *     city?: string,
 * }
 */
final class ReportAddressInput
{
    public ?string $street = null;
    public ?string $city = null;
}
