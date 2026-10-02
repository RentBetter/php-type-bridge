<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ExpandFixtures\Checks\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\ExpandFixtures\Checks\View\CheckView;

/**
 * @phpstan-import-type CheckData from CheckView
 */
final readonly class ListChecksResponse implements HttpOk
{
    public function __construct(
        /** @var list<CheckData> */
        public array $checks,
    ) {}
}
