<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     street: string,
 * }
 */
final class PostalData
{
    #[Assert\NotBlank]
    public ?string $street = null;
}
