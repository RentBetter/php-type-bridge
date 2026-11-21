<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     street: string,
 *     geo?: GeoData,
 * }
 */
final class PostalData
{
    #[Assert\NotBlank]
    public ?string $street = null;

    // Required: left out, Symfony makes it a new GeoData — unless it isn't submitted at all.
    #[Assert\NotNull]
    public ?GeoData $geo = null;
}
