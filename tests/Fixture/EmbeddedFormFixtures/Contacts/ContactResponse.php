<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts;

use PTGS\TypeBridge\Status\HttpCreated;

final class ContactResponse implements HttpCreated
{
    public function __construct(
        public readonly string $id,
    ) {}
}
