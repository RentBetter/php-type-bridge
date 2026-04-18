<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

/**
 * @phpstan-import-type _self from ProjectMutationData as ProjectMutationInput
 *
 * @phpstan-type _self = ProjectMutationInput & array{
 *     ?nickname: string,
 * }
 */
final class CreateProjectInputData extends ProjectMutationData
{
    public ?string $nickname = null;
}
