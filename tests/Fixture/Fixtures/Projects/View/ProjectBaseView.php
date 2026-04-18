<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\View;

use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Enum\ProjectStatus;

/**
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 *     status: value-of<ProjectStatus>,
 * }
 */
final class ProjectBaseView
{
}
