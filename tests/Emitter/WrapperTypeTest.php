<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Parser\GenericType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Shape\ShapeRenderer;
use PTGS\TypeBridge\Support\DomainMapper;
use RuntimeException;

/**
 * A generic a project defines for PHPStan alone — `included<T>`, which its extension reads as
 * `T|Optional<T>` — means nothing to TypeScript beyond the type it wraps. Config names such
 * wrappers; any other unknown generic stays an error.
 */
final class WrapperTypeTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/WrapperFixtures';

    public function test_the_parser_reads_an_unknown_generic_with_its_arguments(): void
    {
        $type = (new PhpDocShapeParser())->parse('included<list<string>>');

        self::assertInstanceOf(GenericType::class, $type);
        self::assertSame('included', $type->name);
        self::assertCount(1, $type->arguments);
        self::assertInstanceOf(ListType::class, $type->arguments[0]);
    }

    public function test_the_parser_reads_several_arguments(): void
    {
        $type = (new PhpDocShapeParser())->parse('pair<string, int>');

        self::assertInstanceOf(GenericType::class, $type);
        self::assertContainsOnlyInstancesOf(ScalarType::class, $type->arguments);
        self::assertCount(2, $type->arguments);
    }

    public function test_a_listed_wrapper_emits_as_the_type_it_wraps(): void
    {
        $output = $this->emit(['included']);

        self::assertStringContainsString(
            "export interface CheckData {\n  name: string;\n  debug?: unknown;\n  documentation?: string;\n  readings?: { label: string }[];\n}",
            $output['Checks'],
        );
    }

    public function test_an_unlisted_generic_is_an_error_that_names_the_config_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/`included<…>`.*wrapperTypes/');

        $this->emit([]);
    }

    public function test_the_config_takes_a_list_of_names(): void
    {
        self::assertSame(['included'], TypeBridgeConfig::fromArray(['wrapperTypes' => ['included']])->wrapperTypes);

        $this->expectException(RuntimeException::class);
        TypeBridgeConfig::fromArray(['wrapperTypes' => ['included' => 'T']]);
    }

    public function test_it_survives_a_render_round_trip(): void
    {
        $parser = new PhpDocShapeParser();
        $original = $parser->parse('array{debug?: included<mixed>, pair: pair<string, int>}');

        self::assertEquals($original, $parser->parse((new ShapeRenderer())->render($original)));
    }

    /**
     * @param list<string> $wrapperTypes
     * @return array<string, string>
     */
    private function emit(array $wrapperTypes): array
    {
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            wrapperTypes: $wrapperTypes,
        ))->emit((new PhpDocTypeCollector())->collect(self::SRC));
    }
}
