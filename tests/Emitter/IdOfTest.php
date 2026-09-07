<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Emitter\EmitterRegistry;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Resolver\EnumResolver;
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
        self::assertStringContainsString("import type { GammaStatusId } from '../Marked/genTypes';", $output['Widgets']);
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
