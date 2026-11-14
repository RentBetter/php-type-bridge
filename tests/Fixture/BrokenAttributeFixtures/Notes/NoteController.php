<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BrokenAttributeFixtures\Notes;

final class NoteController
{
    #[AuditedAttribute]
    public function helper(): void
    {
    }
}
