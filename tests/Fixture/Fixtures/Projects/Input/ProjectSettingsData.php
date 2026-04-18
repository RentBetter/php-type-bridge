<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

/**
 * @phpstan-type _self = array{
 *     notifyOwner: bool,
 *     timezone: string,
 * }
 */
final class ProjectSettingsData
{
    public ?bool $notifyOwner = null;
    public ?string $timezone = null;
}
