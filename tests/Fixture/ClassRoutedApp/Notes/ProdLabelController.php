<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An invokable controller whose class route is confined to an env.
 */
#[Route('/prod-labels/{labelId}', methods: ['PUT'], env: 'prod')]
final class ProdLabelController
{
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Replace a label, in production.')]
    public function __invoke(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
