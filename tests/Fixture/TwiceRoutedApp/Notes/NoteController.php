<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TwiceRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Methods routed twice, the second route ahead of the first in the router's order — a priority
 * sorts it first — and its path ending the way the first's does. What is described is still each
 * method's first #[Route].
 */
final class NoteController
{
    #[Route('/notes/{id}', requirements: ['id' => '[0-9]+'], methods: ['GET'])]
    #[Route('/v1/notes/{id}', methods: ['GET'], priority: 1)]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'A note.')]
    public function showNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * A requirement and a default written inline: the router serves `{page}` holding to `[0-9]+`
     * and `{slug}` defaulting to `intro`, and that is the path a client is given.
     */
    #[Route('/pages/{page<[0-9]+>}/{slug?intro}', methods: ['GET'])]
    #[Route('/legacy/pages/{page}/{slug}', methods: ['GET'], priority: 1)]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'A page.')]
    public function showPageAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * A param mapped to the argument it loads (`{slug:note}`): the router serves `{slug}`.
     */
    #[Route('/notes/by-slug/{slug:note}', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'A note, by its slug.')]
    public function showNoteBySlugAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * A requirement written anchored, as a project's `requirementTypes` may key it: the router
     * takes the anchors off, and the type is the project's all the same.
     */
    #[Route('/anchored/{id}', requirements: ['id' => '^\d+$'], methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'An anchored note.')]
    public function showAnchoredAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
