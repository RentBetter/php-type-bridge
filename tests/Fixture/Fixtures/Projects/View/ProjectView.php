<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\View;

use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Enum\ProjectStatus;

/**
 * @phpstan-import-type _self from \PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\View\TimestampedView as TimestampedData
 * @phpstan-import-type _self from ClientView as ClientData
 *
 * @phpstan-type _self = TimestampedData & array{
 *     name: string,
 *     client: ClientData,
 *     status: value-of<ProjectStatus>,
 *     tags: list<string>,
 *     ?nickname: string,
 * }
 */
final class ProjectView
{
}
