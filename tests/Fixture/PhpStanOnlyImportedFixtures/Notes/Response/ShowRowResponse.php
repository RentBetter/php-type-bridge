<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Model\RowSource;

/**
 * @phpstan-import-type Row from RowSource
 */
final class ShowRowResponse implements HttpOk
{
    public function __construct(
        /** @var Row */
        public readonly array $row,
    ) {}
}
