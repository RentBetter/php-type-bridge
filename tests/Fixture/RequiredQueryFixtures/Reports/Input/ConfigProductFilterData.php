<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * The same, through a project-wide alias from the config.
 *
 * @phpstan-type _self = array{
 *     product: MaybeText,
 * }
 */
final class ConfigProductFilterData
{
    public ?string $product = null;
}
