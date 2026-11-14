<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OtherRouteApp;

final class NoteController
{
    #[Route('/notes/{id}.{!_format}', methods: ['get'])]
    public function exportNoteAction(): void
    {
    }
}
