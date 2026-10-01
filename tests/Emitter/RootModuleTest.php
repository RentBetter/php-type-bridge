<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Emitter\EmitterRegistry;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\Discovered\AlphaStatus;
use PTGS\TypeBridge\Tests\Fixture\Discovered\MarkedEmitter;

/**
 * What every module shares — the config type aliases, the `Including` helper — is declared once
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
        self::assertStringNotContainsString('export type Including', $output['Notes']);

        // The aliases together under their banner, then the helper under its own.
        self::assertStringContainsString("// Aliases\nexport type UuidStr = string;\n\n// Includes\n" . TypeScriptEmitter::INCLUDING_HELPER, $output['']);
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
        self::assertStringContainsString(TypeScriptEmitter::INCLUDING_HELPER, $root);
    }

    public function test_without_one_each_module_declares_its_own(): void
    {
        $output = $this->emitter(new OutputStructure())->emit((new PhpDocTypeCollector())->collect(self::SRC));

        self::assertStringContainsString('export type UuidStr = string;', $output['Notes']);
        self::assertStringContainsString(TypeScriptEmitter::INCLUDING_HELPER, $output['Notes']);
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
