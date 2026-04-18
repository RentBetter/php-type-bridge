<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\View;

/**
 * @phpstan-import-type _self from ProjectBaseView as ProjectBaseData
 * @phpstan-import-type _self from \PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Enum\ProjectStatus as ProjectStatusData
 *
 * @phpstan-type _self = ProjectBaseData & array{
 *     internalNotes: string|null,
 *     auditTrail: list<string>,
 *     statusDetail: ProjectStatusData,
 * }
 */
final class ProjectAdminView
{
}
