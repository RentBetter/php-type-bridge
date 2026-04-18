<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

/**
 * @phpstan-import-type _self from ProjectMutationData as ProjectMutationInput
 *
 * @phpstan-type _self = ProjectMutationInput & array{
 *     ?changeSummary: string,
 * }
 */
final class UpdateProjectInputData extends ProjectMutationData
{
    public ?string $changeSummary = null;
}
