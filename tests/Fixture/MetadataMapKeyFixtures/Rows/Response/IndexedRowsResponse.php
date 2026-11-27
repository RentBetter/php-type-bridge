<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MetadataMapKeyFixtures\Rows\Response;

use PTGS\TypeBridge\Attribute\Metadata;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * Rows beside an index of them: metadata, sent as `$index`, and a map keyed by a project-wide
 * alias of any key.
 */
final class IndexedRowsResponse implements HttpOk
{
    public function __construct(
        /** @var list<string> */
        public readonly array $rows,
        /** @var array<AnyKey, string> */
        #[Metadata]
        public readonly array $index,
    ) {}
}
