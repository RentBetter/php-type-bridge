<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

/**
 * @phpstan-type _self = array{
 *     lat?: string,
 * }
 */
final class GeoData
{
    public ?string $lat = null;
}
