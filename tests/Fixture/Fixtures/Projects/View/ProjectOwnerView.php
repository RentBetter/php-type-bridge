<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\View;

/**
 * @phpstan-import-type _self from ProjectBaseView as ProjectBaseData
 *
 * @phpstan-type _self = ProjectBaseData & array{
 *     canEdit: bool,
 *     ownerNotes: string|null,
 * }
 */
final class ProjectOwnerView
{
}
