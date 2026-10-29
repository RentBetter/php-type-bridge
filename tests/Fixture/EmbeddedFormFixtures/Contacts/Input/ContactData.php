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
 *     tree?: NodeData,
 *     shipping?: PostalData,
 *     memo?: MemoData,
 *     meta?: array{a?: string},
 * }
 */
final class ContactData
{
    #[Assert\NotNull]
    public ?AddressData $address = null;

    #[Assert\NotNull]
    public ?AddressData $billing = null;

    public ?RefData $ref = null;

    // Cascades: PostalData's own constraints are validated.
    #[Assert\NotNull]
    #[Assert\Valid]
    public ?PostalData $postal = null;

    /** @var list<PhoneData> */
    public array $phones = [];

    /** @var list<RefData> */
    public array $refs = [];

    public ?NodeData $tree = null;

    // No cascade: PostalData's own constraints are never validated here.
    #[Assert\NotNull]
    public ?PostalData $shipping = null;

    public ?MemoData $memo = null;

    /** @var array<string, mixed>|null */
    #[Assert\NotNull]
    public ?array $meta = null;
}
