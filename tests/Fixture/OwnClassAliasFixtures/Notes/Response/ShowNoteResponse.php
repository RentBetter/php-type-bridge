<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnClassAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class ShowNoteResponse implements HttpOk
{
    public function __construct(
        /** @var Summary */
        public readonly array $summary,
    ) {}
}
