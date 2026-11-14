<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\HelperNamedEndpointFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

final class NoteResponse implements HttpOk
{
    public function __construct(
        public readonly string $id,
    ) {}
}
