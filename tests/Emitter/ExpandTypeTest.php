<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use RuntimeException;

/**
 * A reference to a related record (`ref<T>`) is sent as the record's id, and as the record itself
 * when the request expands its path (`?expand=`). It emits as `Ref<T>`, which `WithExpands<T, P>`
 * swaps for T at the paths expanded.
 */
final class ExpandTypeTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/ExpandFixtures';

    public function test_a_reference_emits_as_a_ref_to_the_record_it_expands_to(): void
    {
        $output = self::emit(new OutputStructure());

        self::assertStringContainsString(
            "export interface CheckData {\n  name: string;\n  definition?: Ref<DefinitionData>;\n  owner: Ref<DefinitionData>;\n  related: Ref<ResultData>[];\n}",
            $output['Checks'],
        );
        self::assertStringContainsString('latestResult?: Ref<ResultData>;', $output['Checks']);
    }

    public function test_without_a_root_module_the_module_declares_the_expanding_helper(): void
    {
        self::assertStringContainsString(TypeScriptEmitter::WITH_EXPANDS_HELPER, self::emit(new OutputStructure())['Checks']);
    }

    public function test_with_a_root_module_the_helper_lives_there_and_the_module_imports_ref(): void
    {
        $output = self::emit(new OutputStructure(rootModule: 'genTypes.ts'));

        self::assertStringContainsString("// Expands\n" . TypeScriptEmitter::WITH_EXPANDS_HELPER, $output['']);
        self::assertStringNotContainsString('export type Ref', $output['Checks']);
        self::assertStringContainsString("import type { Ref } from '../genTypes';", $output['Checks']);
    }

    public function test_a_module_without_a_reference_declares_no_helper(): void
    {
        $output = (new TypeScriptEmitter(
            enumResolver: new EnumResolver(),
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            includes: new IncludeConvention(['included']),
        ))->emit((new PhpDocTypeCollector())->collect(__DIR__ . '/../Fixture/IncludeFixtures'));

        self::assertStringNotContainsString('export type Ref<', $output['Checks']);
    }

    public function test_an_unlisted_reference_generic_is_an_error_that_names_the_config_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/`ref<…>`.*includes\.refTypes/');

        self::emit(new OutputStructure(), new IncludeConvention());
    }

    public function test_the_config_reads_the_reference_generics(): void
    {
        self::assertSame(['ref'], TypeBridgeConfig::fromArray(['includes' => ['refTypes' => ['ref']]])->includes->refTypes);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/includes\.refTypes/');
        TypeBridgeConfig::fromArray(['includes' => ['refTypes' => 'ref']]);
    }

    /**
     * @return array<string, string>
     */
    private static function emit(OutputStructure $structure, IncludeConvention $includes = new IncludeConvention(refTypes: ['ref'])): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output', $structure),
            includes: $includes,
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );
    }
}
