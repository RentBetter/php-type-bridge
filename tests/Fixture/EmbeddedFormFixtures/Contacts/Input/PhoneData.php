<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

/**
 * @phpstan-type _self = array{
 *     number: string,
 *     label?: string|null,
 * }
 */
final class PhoneData
{
    public ?string $number = null;

    public ?string $label = null;
}
