<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\ImportStrategy;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Config\SegmentCase;
use PTGS\TypeBridge\Emitter\DomainAssembler;
use PTGS\TypeBridge\Emitter\EmitImport;
use PTGS\TypeBridge\Emitter\EnumIdSymbolResolver;
use PTGS\TypeBridge\Emitter\EmitterRegistry;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Sorting\AlphabeticalOrder;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Support\RootSources;
use PTGS\TypeBridge\Tests\Fixture\Discovered\AlphaStatus;
use PTGS\TypeBridge\Tests\Fixture\Discovered\BetaStatus;
use PTGS\TypeBridge\Tests\Fixture\Discovered\MarkedEmitter;

final class DiscoveredEmitterTest extends TestCase
{
    public function test_discovered_emitter_produces_domain_and_root_modules(): void
    {
        $registry = EmitterRegistry::fromAttributeScan([MarkedEmitter::class]);
        $emitter = new TypeScriptEmitter(
            enumResolver: new EnumResolver(),
            domainMapper: new DomainMapper('/tmp/type-bridge-output', new OutputStructure(
                segmentCase: SegmentCase::PerSegmentLcFirst,
                rootModule: 'genTypes.ts',
                importStrategy: ImportStrategy::Alias,
                aliasBase: '@/api/genTypes',
            )),
            registry: $registry,
        );

        $output = $emitter->emitDiscovered([AlphaStatus::class, BetaStatus::class]);

        self::assertArrayHasKey('Marked', $output);
        self::assertArrayHasKey('', $output);

        $domain = $output['Marked'];
        self::assertStringContainsString("import type { Base } from '@/api/genTypes';", $domain);
        self::assertStringContainsString("export type AlphaStatusId = 'OPEN' | 'CLOSED';", $domain);
        self::assertStringContainsString("export type BetaStatusId = 'DRAFT' | 'LIVE';", $domain);
        self::assertStringNotContainsString('// Enums', $domain);

        self::assertStringContainsString('export interface Base {', $output['']);
    }

    /**
     * A class under a root source is declared in the root module whichever emitter writes it, and
     * the symbol its emitter publishes for it points there too.
     */
    public function test_a_root_sources_class_is_re_homed_to_the_root_module(): void
    {
        $registry = EmitterRegistry::fromAttributeScan([MarkedEmitter::class]);
        $rootSources = new RootSources(new DomainGuesser(rootSources: ['Discovered/AlphaStatus.php']), (string) realpath(__DIR__ . '/../Fixture'));
        $emitter = new TypeScriptEmitter(
            enumResolver: new EnumResolver(),
            domainMapper: new DomainMapper('/tmp/type-bridge-output', new OutputStructure(rootModule: 'genTypes.ts')),
            registry: $registry,
            rootSources: $rootSources,
        );

        $output = $emitter->emitDiscovered([AlphaStatus::class, BetaStatus::class]);

        self::assertStringContainsString("export type AlphaStatusId = 'OPEN' | 'CLOSED';", $output['']);
        self::assertStringContainsString('export interface Base {', $output['']);
        self::assertStringNotContainsString('AlphaStatusId', $output['Marked']);
        self::assertStringContainsString('BetaStatusId', $output['Marked']);

        $ids = new EnumIdSymbolResolver(new EnumResolver(), $registry, $rootSources);
        self::assertEquals(new EmitImport('', 'AlphaStatusId'), $ids->resolve(AlphaStatus::class));
        self::assertEquals(new EmitImport('Marked', 'BetaStatusId'), $ids->resolve(BetaStatus::class));
    }

    public function test_returns_empty_without_discovered_emitters(): void
    {
        $emitter = new TypeScriptEmitter(new EnumResolver(), new DomainMapper('/tmp/type-bridge-output'));

        self::assertSame([], $emitter->emitDiscovered([AlphaStatus::class]));
    }

    public function test_alphabetical_declaration_sort_orders_blocks_by_name(): void
    {
        $emitter = new TypeScriptEmitter(
            enumResolver: new EnumResolver(),
            domainMapper: new DomainMapper('/tmp/type-bridge-output', new OutputStructure(
                rootModule: 'genTypes.ts',
                importStrategy: ImportStrategy::Alias,
                aliasBase: '@/api/genTypes',
            )),
            registry: EmitterRegistry::fromAttributeScan([MarkedEmitter::class]),
            assembler: new DomainAssembler('// AUTO-GENERATED. DO NOT EDIT.', new AlphabeticalOrder()),
        );

        // Fed in reverse; the alphabetical strategy must order AlphaStatus before BetaStatus.
        $domain = $emitter->emitDiscovered([BetaStatus::class, AlphaStatus::class])['Marked'];

        $alphaPosition = strpos($domain, 'AlphaStatusId');
        $betaPosition = strpos($domain, 'BetaStatusId');
        self::assertIsInt($alphaPosition);
        self::assertIsInt($betaPosition);
        self::assertLessThan($betaPosition, $alphaPosition);
    }

    /**
     * A module both passes write — a subdomain whose shapes emit() declares and whose enums a
     * discovered emitter claims — holds both. Writing it twice replaced the shapes with the enums.
     */
    public function test_a_module_both_passes_write_keeps_both(): void
    {
        $emitter = new TypeScriptEmitter(
            enumResolver: new EnumResolver(),
            domainMapper: new DomainMapper('/tmp/type-bridge-output', new OutputStructure(
                rootModule: 'genTypes.ts',
                importStrategy: ImportStrategy::Alias,
                aliasBase: '@/api/genTypes',
            )),
            registry: EmitterRegistry::fromAttributeScan([MarkedEmitter::class]),
        );

        $marked = new CollectedDomain('Marked');
        $marked->types['MarkedView'] = new CollectedType(
            name: 'MarkedView',
            definition: 'array{label: string}',
            parsed: (new PhpDocShapeParser())->parse('array{label: string}'),
            sourceFile: __FILE__,
            domain: 'Marked',
            ownerClass: self::class,
        );
        $emitter->emit(['Marked' => $marked]);

        $module = $emitter->emitDiscovered([AlphaStatus::class])['Marked'];

        self::assertStringContainsString("export interface MarkedView {\n  label: string;\n}", $module);
        self::assertStringContainsString("export type AlphaStatusId = 'OPEN' | 'CLOSED';", $module);
        self::assertStringContainsString("import type { Base } from '@/api/genTypes';", $module);
    }
}
