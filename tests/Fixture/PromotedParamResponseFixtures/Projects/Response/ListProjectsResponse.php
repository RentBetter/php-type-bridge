<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PromotedParamResponseFixtures\Projects\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\PromotedParamResponseFixtures\Projects\View\ProjectView;

/**
 * A response typed the way the spec writes one: by the constructor's `@param` tags, with no
 * `@var` on the promoted properties themselves.
 *
 * @phpstan-import-type _self from ProjectView as ProjectData
 */
final class ListProjectsResponse implements HttpOk
{
    /**
     * @param list<ProjectData> $projects
     * @param list<array{path: string, message: string}> $warnings
     */
    public function __construct(
        public readonly array $projects,
        public readonly array $warnings,
        public readonly int $total,
    ) {}
}
