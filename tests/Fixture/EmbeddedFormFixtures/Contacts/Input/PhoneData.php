<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     number: string,
 *     label?: string|null,
 * }
 */
final class PhoneData
{
    // Never validated: `phones` doesn't cascade.
    #[Assert\Length(max: 20)]
    public ?string $number = null;

    public ?string $label = null;
}
