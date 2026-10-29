<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Form\ContactType;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController
{
    #[Route('/contacts', methods: ['POST'])]
    #[ApiRequest(body: ContactType::class)]
    #[ApiResponses([ContactResponse::class])]
    #[McpTool(description: 'Add a contact.')]
    public function createContactAction(): ContactResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
