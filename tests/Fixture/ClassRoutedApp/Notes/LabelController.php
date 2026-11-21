<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A class-level #[Route] that gives every method its HTTP method: a method naming none is served
 * with the class's.
 */
#[Route('/labels', methods: ['PUT'])]
final class LabelController
{
    #[Route('/{labelId}')]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Replace a label.')]
    public function replaceLabelAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
