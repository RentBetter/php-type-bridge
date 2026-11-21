<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportedAliasNameFixtures\Lists;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\ImportedAliasNameFixtures\Lists\Form\ListFilterType;
use Symfony\Component\Routing\Attribute\Route;

final class ListController
{
    #[Route('/lists', methods: ['GET'])]
    #[ApiRequest(query: ListFilterType::class)]
    #[ApiResponses([ListResponse::class])]
    public function listAction(): ListResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
