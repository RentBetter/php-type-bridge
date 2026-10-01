<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Parser\GenericType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Shape\ShapeRenderer;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\IncludeFixtures\Checks\Attribute\SideLoad;
use RuntimeException;

/**
 * What a response only sends when the request asks for it (`?include=`). A key is absent unless
 * asked for; whether it is then guaranteed is what the PHP spelling says — a required key, or a
 * side-load, always is; an optional one may still be absent. The `…Included` union lists the
 * guaranteed ones, and `Including<T, K>` makes them present.
 */
final class IncludeTypeTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/IncludeFixtures';

    /** @var array<string, string> */
    private static array $output;

    public static function setUpBeforeClass(): void
    {
        self::$output = self::emit(new IncludeConvention(['included'], SideLoad::class));
    }

    public function test_the_parser_reads_an_unknown_generic_with_its_arguments(): void
    {
        $type = (new PhpDocShapeParser())->parse('included<list<string>>');

        self::assertInstanceOf(GenericType::class, $type);
        self::assertSame('included', $type->name);
        self::assertInstanceOf(ListType::class, $type->arguments[0]);

        $pair = (new PhpDocShapeParser())->parse('pair<string, int>');
        self::assertInstanceOf(GenericType::class, $pair);
        self::assertContainsOnlyInstancesOf(ScalarType::class, $pair->arguments);
    }

    public function test_an_included_key_is_optional_and_a_required_one_is_guaranteed_when_asked_for(): void
    {
        self::assertStringContainsString(
            "export interface CheckData {\n  name: string;\n  debug?: unknown;\n  documentation?: string;\n  lastRun?: { at: string; readings?: string[] };\n}\n\nexport type CheckDataIncluded = 'debug';",
            self::$output['Checks'],
        );
    }

    public function test_a_side_load_and_a_required_included_property_are_guaranteed_when_asked_for(): void
    {
        self::assertStringContainsString(
            "export interface ListChecksResponse {\n  checks: CheckData[];\n  definitions?: { id: string }[];\n  total?: number;\n  cursor?: string;\n}\n\nexport type ListChecksResponseIncluded = 'definitions' | 'total';",
            self::$output['Checks'],
        );
    }

    public function test_a_module_with_an_included_union_declares_the_including_helper(): void
    {
        self::assertStringContainsString(TypeScriptEmitter::INCLUDING_HELPER, self::$output['Checks']);
    }

    public function test_an_unlisted_generic_is_an_error_that_names_the_config_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/`included<…>`.*includes\.types/');

        self::emit(new IncludeConvention());
    }

    public function test_the_config_reads_the_include_convention(): void
    {
        $includes = TypeBridgeConfig::fromArray(['includes' => ['types' => ['included'], 'sideLoadAttribute' => SideLoad::class]])->includes;

        self::assertSame(['included'], $includes->types);
        self::assertSame(SideLoad::class, $includes->sideLoadAttribute);

        $this->expectException(RuntimeException::class);
        TypeBridgeConfig::fromArray(['includes' => ['types' => ['included' => 'T']]]);
    }

    public function test_it_survives_a_render_round_trip(): void
    {
        $parser = new PhpDocShapeParser();
        $original = $parser->parse('array{debug: included<mixed>, pair: pair<string, int>}');

        self::assertEquals($original, $parser->parse((new ShapeRenderer())->render($original)));
    }

    /**
     * @return array<string, string>
     */
    private static function emit(IncludeConvention $includes): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            includes: $includes,
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );
    }
}
