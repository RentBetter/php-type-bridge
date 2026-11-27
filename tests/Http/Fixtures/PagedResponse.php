<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Fixtures;

use PTGS\TypeBridge\Attribute\Metadata;
use PTGS\TypeBridge\Contract\ApiSuccessResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * A list beside metadata about it, which is sent behind a `$`.
 */
final readonly class PagedResponse implements ApiSuccessResponse, HttpOk
{
    /**
     * @param list<string>         $ids
     * @param array<string, mixed> $pagination
     */
    public function __construct(
        public array $ids,
        #[Metadata]
        public array $pagination,
    ) {}
}
