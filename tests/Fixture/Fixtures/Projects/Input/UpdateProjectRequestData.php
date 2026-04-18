<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

/**
 * @phpstan-import-type _self from UpdateProjectInputData as UpdateProjectInput
 *
 * @phpstan-type _self = array{
 *     project: UpdateProjectInput,
 * }
 */
final class UpdateProjectRequestData
{
    public ?UpdateProjectInputData $project = null;
}
