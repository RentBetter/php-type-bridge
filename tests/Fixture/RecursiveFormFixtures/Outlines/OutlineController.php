<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form\BookType;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form\CommentType;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form\OutlineType;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form\TreeType;
use Symfony\Component\Routing\Attribute\Route;

final class OutlineController
{
    #[Route('/outlines', methods: ['POST'])]
    #[ApiRequest(body: OutlineType::class)]
    #[ApiResponses([OutlineResponse::class])]
    #[McpTool(description: 'Add an outline.')]
    public function createOutlineAction(): OutlineResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/trees', methods: ['POST'])]
    #[ApiRequest(body: TreeType::class)]
    #[ApiResponses([OutlineResponse::class])]
    #[McpTool(description: 'Add a tree.')]
    public function createTreeAction(): OutlineResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/trees', methods: ['GET'])]
    #[ApiRequest(query: TreeType::class)]
    #[ApiResponses([OutlineResponse::class])]
    #[McpTool(description: 'Find a tree by its shape.')]
    public function findTreeAction(): OutlineResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/comments', methods: ['POST'])]
    #[ApiRequest(body: CommentType::class)]
    #[ApiResponses([OutlineResponse::class])]
    #[McpTool(description: 'Add a comment and its replies.')]
    public function createCommentAction(): OutlineResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/books', methods: ['POST'])]
    #[ApiRequest(body: BookType::class)]
    #[ApiResponses([OutlineResponse::class])]
    #[McpTool(description: 'Add a book.')]
    public function createBookAction(): OutlineResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
