<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Emitter\EmitterRegistry;
use PTGS\TypeBridge\Emitter\EnumIdSymbolResolver;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Shape\ShapeRenderer;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\Discovered\MarkedEmitter;
use RuntimeException;

/**
 * `id-of<Enum>` names an enum's stable string ids. Unlike `value-of<Enum>` it does not
 * derive them — an emitter publishes the union and this only points at it.
 */
final class IdOfTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/IdOfFixtures';

    private const string ENUMS = __DIR__ . '/../Fixture/Discovered';

    public function test_the_parser_reads_it(): void
    {
        $parsed = (new PhpDocShapeParser())->parse('array{status: id-of<GammaStatus>}');

        self::assertInstanceOf(ShapeType::class, $parsed);
        $status = $parsed->fields[0]->type;
        self::assertInstanceOf(IdOfType::class, $status);
        self::assertSame('GammaStatus', $status->enumClass);
    }

    public function test_it_references_the_published_union_and_imports_it(): void
    {
        $output = $this->emit();

        self::assertStringContainsString('status: GammaStatusId;', $output['Widgets']);
        self::assertStringContainsString("GammaStatusId } from '../Marked/genTypes';", $output['Widgets']);
    }

    /**
     * The union is emitted by the Discovered pass, so referencing it must not produce a
     * second declaration here for the two to drift apart.
     */
    public function test_it_declares_nothing_of_its_own(): void
    {
        self::assertStringNotContainsString('export type GammaStatusId', $this->emit()['Widgets']);
    }

    public function test_an_enum_no_emitter_publishes_ids_for_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No emitter publishes stable ids for');

        $this->emit(registry: EmitterRegistry::default());
    }


    public function test_the_parser_reads_a_fully_qualified_name(): void
    {
        $parsed = (new PhpDocShapeParser())->parse('array{status: id-of<\\Vendor\\Domain\\Thing>}');

        self::assertInstanceOf(ShapeType::class, $parsed);
        $status = $parsed->fields[0]->type;
        self::assertInstanceOf(IdOfType::class, $status);
        self::assertSame('\\Vendor\\Domain\\Thing', $status->enumClass);
    }

    public function test_the_parser_reads_it_nested_in_a_list(): void
    {
        $parsed = (new PhpDocShapeParser())->parse('array{statuses: list<id-of<GammaStatus>>}');

        self::assertInstanceOf(ShapeType::class, $parsed);
        $statuses = $parsed->fields[0]->type;
        self::assertInstanceOf(ListType::class, $statuses);
        self::assertInstanceOf(IdOfType::class, $statuses->inner);
    }

    public function test_it_survives_a_render_round_trip(): void
    {
        $shape = 'array{status: id-of<GammaStatus>}';

        $rendered = (new ShapeRenderer())->render((new PhpDocShapeParser())->parse($shape));

        self::assertSame($shape, $rendered);
    }

    /** A string-backed enum is no different: the ids are the emitter's, not the backing values. */
    public function test_it_works_for_a_string_backed_enum(): void
    {
        $output = $this->emit();

        self::assertStringContainsString('primary: AlphaStatusId;', $output['Widgets']);
        self::assertStringContainsString('statuses: AlphaStatusId[];', $output['Widgets']);
    }

    public function test_it_reaches_inside_lists_and_optional_fields(): void
    {
        $output = $this->emit();

        self::assertStringContainsString('statuses: AlphaStatusId[];', $output['Widgets']);
        self::assertStringContainsString('stage?: BetaStatusId;', $output['Widgets']);
    }

    /** Named by FQCN, the symbol is still the short-named one the emitter publishes. */
    public function test_a_fully_qualified_name_resolves_to_the_same_symbol(): void
    {
        self::assertStringContainsString('stage?: BetaStatusId;', $this->emit()['Widgets']);
    }

    public function test_each_enum_is_imported_once_however_often_it_appears(): void
    {
        $widgets = $this->emit()['Widgets'];

        self::assertSame(1, substr_count($widgets, 'import type {'), $widgets);
        self::assertStringContainsString(
            "import type { AlphaStatusId, BetaStatusId, GammaStatusId } from '../Marked/genTypes';",
            $widgets,
        );
    }

    /** Published into the referencing module's own domain, there is nothing to import. */
    public function test_it_does_not_import_from_its_own_domain(): void
    {
        $marked = $this->emit()['Marked'];

        self::assertStringContainsString('status: GammaStatusId;', $marked);
        self::assertStringNotContainsString('import type {', $marked);
    }

    public function test_a_name_that_is_not_an_enum_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not name an enum');

        (new EnumIdSymbolResolver(
            new EnumResolver(),
            EmitterRegistry::fromAttributeScan([MarkedEmitter::class]),
        ))->resolve('NotAnEnumAnywhere');
    }

    /**
     * @return array<string, string>
     */
    private function emit(?EmitterRegistry $registry = null): array
    {
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::ENUMS);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            registry: $registry ?? EmitterRegistry::fromAttributeScan([MarkedEmitter::class]),
        ))->emit((new PhpDocTypeCollector())->collect(self::SRC));
    }
}
