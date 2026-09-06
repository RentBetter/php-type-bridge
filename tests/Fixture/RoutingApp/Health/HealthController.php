<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RoutingApp\Health;

use Symfony\Component\Routing\Attribute\Route;

/** No prefix in routes.yaml, and invokable — the `_controller` default is the bare FQCN. */
final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): void {}
}
