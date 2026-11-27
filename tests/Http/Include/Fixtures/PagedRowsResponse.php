<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include\Fixtures;

use PTGS\TypeBridge\Attribute\Metadata;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * Rows beside metadata about them, which is sent behind a `$`.
 */
final readonly class PagedRowsResponse implements HttpOk
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $page
     */
    public function __construct(
        public array $rows,
        #[Metadata]
        public array $page,
    ) {}
}
