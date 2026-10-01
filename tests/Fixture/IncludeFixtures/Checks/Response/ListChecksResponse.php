<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IncludeFixtures\Checks\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\IncludeFixtures\Checks\Attribute\SideLoad;
use PTGS\TypeBridge\Tests\Fixture\IncludeFixtures\Checks\View\CheckView;

/**
 * @phpstan-import-type CheckData from CheckView
 */
final readonly class ListChecksResponse implements HttpOk
{
    public function __construct(
        /** @var list<CheckData> */
        public array $checks,
        /** @var ?list<array{id: string}> */
        #[SideLoad]
        public ?array $definitions = null,
        /** @var included<int> */
        public mixed $total = null,
        /** @var ?included<string> */
        public mixed $cursor = null,
    ) {}
}
