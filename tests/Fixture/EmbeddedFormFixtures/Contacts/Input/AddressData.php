<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

/**
 * @phpstan-type _self = array{
 *     street: string,
 *     line2?: string,
 * }
 */
final class AddressData
{
    public ?string $street = null;

    public ?string $line2 = null;
}
