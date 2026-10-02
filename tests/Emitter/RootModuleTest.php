<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Emitter\Builtin\EndpointContractEmitter;
use PTGS\TypeBridge\Emitter\EmitterRegistry;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\Discovered\AlphaStatus;
use PTGS\TypeBridge\Tests\Fixture\Discovered\MarkedEmitter;

/**
 * What every module shares — the config type aliases, the `WithIncludes` helper — is declared once
 * in the root module when there is one, and imported from it; and a module imports only what it
 * uses.
 */
final class RootModuleTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/RootModuleFixtures';

    public function test_with_a_root_module_the_shared_declarations_live_there_and_are_imported(): void
    {
        $output = $this->emitter(new OutputStructure(rootModule: 'genTypes.ts'))->emit((new PhpDocTypeCollector())->collect(self::SRC));

        self::assertStringContainsString("import type { UuidStr } from '../genTypes';", $output['Notes']);
        self::assertStringNotContainsString('export type UuidStr', $output['Notes']);
        self::assertStringNotContainsString('export type WithIncludes', $output['Notes']);

        // The aliases together under their banner, then the helper under its own.
        self::assertStringContainsString("// Aliases\nexport type UuidStr = string;\n\n// Includes\n" . TypeScriptEmitter::WITH_INCLUDES_HELPER, $output['']);
    }

    /**
     * The discovered pass writes the root module too; it must keep what emit() put there.
     */
    public function test_the_discovered_pass_keeps_them_in_the_root_module_it_writes(): void
    {
        $emitter = $this->emitter(new OutputStructure(rootModule: 'genTypes.ts'), EmitterRegistry::fromAttributeScan([MarkedEmitter::class]));
        $emitter->emit((new PhpDocTypeCollector())->collect(self::SRC));
        $root = $emitter->emitDiscovered([AlphaStatus::class])[''];

        self::assertStringContainsString('export interface Base', $root);
        self::assertStringContainsString('export type UuidStr = string;', $root);
        self::assertStringContainsString(TypeScriptEmitter::WITH_INCLUDES_HELPER, $root);
    }

    public function test_endpoint_result_is_declared_once_in_the_root_module_and_imported(): void
    {
        $src = __DIR__ . '/../Fixture/Fixtures';
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        $output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output', new OutputStructure(rootModule: 'genTypes.ts')),
            preserveNull: ['ProjectAdminView.internalNotes'],
        ))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responseCollector->collect($src),
            (new EndpointContractCollector())->collect($src, $responseCollector->collectIndex($src)),
        );

        self::assertStringContainsString(EndpointContractEmitter::RESULT_HELPER, $output['']);
        foreach ($output as $domain => $module) {
            if ('' === $domain || !str_contains($module, 'EndpointResult<')) {
                continue;
            }
            self::assertStringNotContainsString('export type EndpointResult', $module, "{$domain} declares its own");
            self::assertMatchesRegularExpression("/import type \\{[^}]*\\bEndpointResult\\b[^}]*\\} from '\\.\\.\\/genTypes';/", $module, "{$domain} imports it");
        }
    }

    /**
     * A type under a root source is declared in the root module beside the aliases: a domain
     * imports it from there, and the root module imports what it uses from a domain.
     */
    public function test_a_root_source_declares_its_types_in_the_root_module(): void
    {
        $collected = (new PhpDocTypeCollector(domainGuesser: new DomainGuesser(rootSources: ['Shared'])))->collect(self::SRC);
        $output = $this->emitter(new OutputStructure(rootModule: 'genTypes.ts'))->emit($collected);

        self::assertArrayNotHasKey('Shared', $output);
        self::assertStringContainsString('export interface Other', $output['']);
        self::assertStringContainsString('export interface Pointer', $output['']);
        self::assertStringContainsString('export type UuidStr = string;', $output['']);
        self::assertStringContainsString("import type { LinkData } from './Links/genTypes';", $output['']);
        self::assertStringNotContainsString("from '../genTypes'", $output[''], 'The root module imports nothing from itself');
        self::assertStringContainsString("import type { Other } from '../genTypes';", $output['Links']);
    }

    public function test_the_discovered_pass_keeps_a_root_sources_types(): void
    {
        $emitter = $this->emitter(new OutputStructure(rootModule: 'genTypes.ts'), EmitterRegistry::fromAttributeScan([MarkedEmitter::class]));
        $emitter->emit((new PhpDocTypeCollector(domainGuesser: new DomainGuesser(rootSources: ['Shared'])))->collect(self::SRC));
        $root = $emitter->emitDiscovered([AlphaStatus::class])[''];

        self::assertStringContainsString('export interface Base', $root);
        self::assertStringContainsString('export interface Other', $root);
        self::assertStringContainsString("import type { LinkData } from './Links/genTypes';", $root);
        self::assertStringContainsString('export type UuidStr = string;', $root);
    }

    /**
     * An endpoint whose success response has a body takes the include query parameters: its query
     * is its form's type and `IncludeQuery`, or `IncludeQuery` alone, declared once in the root
     * module with each parameter's description. One answering 204 takes none.
     */
    public function test_an_endpoint_with_a_response_body_takes_the_include_query(): void
    {
        $src = __DIR__ . '/../Fixture/Fixtures';
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        $output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output', new OutputStructure(rootModule: 'genTypes.ts')),
            preserveNull: ['ProjectAdminView.internalNotes'],
            includes: new IncludeConvention(query: ['include' => 'Opt-in parts.', 'expand' => 'Records */ in place.']),
        ))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responseCollector->collect($src),
            (new EndpointContractCollector())->collect($src, $responseCollector->collectIndex($src)),
        );

        self::assertStringContainsString("export interface IncludeQuery {\n  /** Opt-in parts. */\n  include?: string;\n  /** Records *\\/ in place. */\n  expand?: string;\n}", $output['']);

        $projects = $output['Projects'];
        self::assertMatchesRegularExpression('/export type ListProjectsQuery = \\w+ & IncludeQuery;/', $projects);
        self::assertStringContainsString('export type ShowProjectQuery = IncludeQuery;', $projects);
        self::assertStringContainsString('query?: ShowProjectQuery', $projects);
        self::assertStringNotContainsString('DeleteProjectQuery', $projects);
        self::assertMatchesRegularExpression("/import type \\{[^}]*\\bIncludeQuery\\b[^}]*\\} from '\\.\\.\\/genTypes';/", $projects);
    }

    public function test_without_one_each_module_declares_its_own(): void
    {
        $output = $this->emitter(new OutputStructure())->emit((new PhpDocTypeCollector())->collect(self::SRC));

        self::assertStringContainsString('export type UuidStr = string;', $output['Notes']);
        self::assertStringContainsString(TypeScriptEmitter::WITH_INCLUDES_HELPER, $output['Notes']);
        self::assertArrayNotHasKey('', $output);
    }

    public function test_an_import_no_emitted_shape_uses_is_left_out(): void
    {
        $output = $this->emitter(new OutputStructure())->emit((new PhpDocTypeCollector())->collect(self::SRC));

        self::assertStringNotContainsString('import type { Other }', $output['Notes']);
    }

    private function emitter(OutputStructure $structure, ?EmitterRegistry $registry = null): TypeScriptEmitter
    {
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        return new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output', $structure),
            registry: $registry,
            typeAliases: ['UuidStr' => 'string'],
            includes: new IncludeConvention(['included']),
        );
    }
}
