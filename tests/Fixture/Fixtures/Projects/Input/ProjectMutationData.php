<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Enum\ProjectStatus;

/**
 * @phpstan-import-type _self from ProjectSettingsData as ProjectSettingsInput
 *
 * @phpstan-type _self = array{
 *     name: string,
 *     clientId: string,
 *     status: value-of<ProjectStatus>,
 *     settings: ProjectSettingsInput,
 * }
 */
class ProjectMutationData
{
    public ?string $name = null;
    public ?string $clientId = null;
    public ?ProjectStatus $status = null;
    public ?ProjectSettingsData $settings = null;
}
