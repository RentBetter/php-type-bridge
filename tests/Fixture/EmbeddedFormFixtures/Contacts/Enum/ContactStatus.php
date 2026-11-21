<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Enum;

enum ContactStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
