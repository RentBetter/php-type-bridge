<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include\Fixtures;

use PTGS\TypeBridge\Status\HttpOk;

final readonly class RowsResponse implements HttpOk
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public array $rows,
        public mixed $summary = null,
    ) {}
}
