<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     address?: AddressData,
 *     billing?: AddressData,
 *     ref?: RefData,
 *     postal?: PostalData,
 *     phones?: list<PhoneData>,
 *     refs?: list<RefData>,
 * }
 */
final class ContactData
{
    #[Assert\NotNull]
    public ?AddressData $address = null;

    #[Assert\NotNull]
    public ?AddressData $billing = null;

    public ?RefData $ref = null;

    #[Assert\NotNull]
    public ?PostalData $postal = null;

    /** @var list<PhoneData> */
    public array $phones = [];

    /** @var list<RefData> */
    public array $refs = [];
}
