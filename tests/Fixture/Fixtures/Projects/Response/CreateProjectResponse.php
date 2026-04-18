<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response;

use PTGS\TypeBridge\Status\HttpCreated;

/**
 * @phpstan-import-type _self from \PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\View\ProjectView as ProjectData
 */
final class CreateProjectResponse implements HttpCreated
{
    public function __construct(
        /** @var ProjectData */
        public readonly array $project,
    ) {}
}
