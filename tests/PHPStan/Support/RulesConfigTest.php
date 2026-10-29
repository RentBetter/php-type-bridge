<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Support;

use PHPStan\Testing\PHPStanTestCase;
use PTGS\TypeBridge\PHPStan\Support\ApiMethodInspector;
use PTGS\TypeBridge\PHPStan\Support\FormContractValidator;
use PTGS\TypeBridge\Routing\RoutePathResolver;

/**
 * The services the package's `rules.neon` registers are ones PHPStan's container can build, as a
 * project including it builds them: each constructor argument a service of its own or configured.
 */
final class RulesConfigTest extends PHPStanTestCase
{
    public function testTheRulesServicesAreBuilt(): void
    {
        $container = self::getContainer();

        self::assertInstanceOf(ApiMethodInspector::class, $container->getByType(ApiMethodInspector::class));
        self::assertInstanceOf(RoutePathResolver::class, $container->getByType(RoutePathResolver::class));
        self::assertInstanceOf(FormContractValidator::class, $container->getByType(FormContractValidator::class));
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../../rules.neon'];
    }
}
