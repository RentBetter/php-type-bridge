<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * A response property typed by the constructor's `@param` — as the spec writes every response —
 * emits that type. Read from `@var` alone it fell back to the native `array`, which named no
 * TypeScript symbol and stopped generation.
 */
final class ResponseClassCollectorPromotedParamTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/PromotedParamResponseFixtures';

    public function test_a_promoted_property_emits_its_constructor_param_type(): void
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        $output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );

        self::assertStringContainsString("export interface ListProjectsResponse {\n  projects: ProjectView[];\n  warnings: { path: string; message: string }[];\n  total: number;\n}", $output['Projects']);
    }
}
