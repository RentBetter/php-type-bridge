<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnroutedMcpFixtures\Health;

use Symfony\Component\Routing\Attribute\Route;

/** The only controller routes.yaml imports — so the routing loads, and Ping is simply not in it. */
final class HealthController
{
    #[Route('/health', methods: ['GET'])]
    public function __invoke(): void {}
}
