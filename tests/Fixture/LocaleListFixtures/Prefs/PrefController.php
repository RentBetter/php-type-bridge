<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LocaleListFixtures\Prefs;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class PrefController
{
    #[Route('/prefs', methods: ['POST'])]
    #[ApiRequest(body: PrefType::class)]
    #[ApiResponses([PrefResponse::class])]
    #[McpTool(description: 'Save the preferences.')]
    public function savePrefsAction(): PrefResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
