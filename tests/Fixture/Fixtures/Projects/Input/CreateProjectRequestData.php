<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

/**
 * @phpstan-import-type _self from CreateProjectInputData as CreateProjectInput
 *
 * @phpstan-type _self = array{
 *     project: CreateProjectInput,
 * }
 */
final class CreateProjectRequestData
{
    public ?CreateProjectInputData $project = null;
}
