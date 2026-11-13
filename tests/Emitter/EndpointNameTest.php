<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Support\RootSources;
use RuntimeException;

/**
 * What a module declares for its endpoints — the helpers they share, each endpoint's inputs,
 * result and map — no other type of the module may be named, and an import of that name is
 * aliased.
 */
final class EndpointNameTest extends TestCase
{
    public function test_a_type_named_as_an_endpoint_helper_is_a_collision(): void
    {
        // The module declares `Endpoint`, which its endpoints are typed with, and a class's `_self`
        // of the same name: two declarations, one name.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "Notes": "Endpoint"');

        $this->emit(__DIR__ . '/../Fixture/EndpointHelperClashFixtures');
    }

    public function test_a_root_source_named_as_an_endpoint_helper_is_a_collision_in_the_root_module(): void
    {
        // With a root module the helpers are declared there, once, beside the root sources' types.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "": "EndpointResult"');

        $this->emit(__DIR__ . '/../Fixture/RootHelperClashFixtures', new OutputStructure(rootModule: 'genTypes.ts'), ['Shared']);
    }

    public function test_a_root_source_with_endpoints_of_its_own_takes_the_helpers_it_declares(): void
    {
        // The root module's own endpoints and every other module's take the same helpers, declared
        // there once: no collision of their own.
        $root = $this->emit(__DIR__ . '/../Fixture/RootEndpointFixtures', new OutputStructure(rootModule: 'genTypes.ts'), ['Shared'])[''] ?? '';

        self::assertSame(1, substr_count($root, 'export interface Endpoint<'));
    }

    public function test_an_import_named_as_an_endpoints_include_query_is_aliased(): void
    {
        // ShowNote takes no query form, but `?include=`: its query is declared `ShowNoteQuery`.
        $notes = $this->emit(__DIR__ . '/../Fixture/IncludeQueryClashFixtures', includes: new IncludeConvention(query: ['include' => 'Opt-in parts.']))['Notes'] ?? '';

        self::assertSame(1, substr_count($notes, 'export type ShowNoteQuery ='));
        self::assertStringContainsString('ShowNoteQuery as SharedShowNoteQuery', $notes);
        self::assertStringContainsString('q: SharedShowNoteQuery', $notes);
    }

    public function test_an_import_named_as_an_endpoints_route_path_is_aliased(): void
    {
        // GetNote's path, read from its route, is declared `GetNotePathParams`; Shared's type of that name,
        // imported into the same module, is given another name.
        $notes = $this->emit(__DIR__ . '/../Fixture/PathAliasClashFixtures')['Notes'] ?? '';

        self::assertSame(1, substr_count($notes, 'export type GetNotePathParams ='));
        self::assertStringContainsString('GetNotePathParams as SharedGetNotePathParams', $notes);
        self::assertStringContainsString('at: SharedGetNotePathParams', $notes);
    }

    /**
     * @param list<string> $rootSources
     *
     * @return array<string, string>
     */
    private function emit(string $src, OutputStructure $output = new OutputStructure(), array $rootSources = [], IncludeConvention $includes = new IncludeConvention()): array
    {
        $domains = new DomainGuesser(rootSources: $rootSources);
        $responses = new ResponseClassCollector(domainGuesser: $domains);
        $enums = new EnumResolver(domainGuesser: $domains);
        $enums->scanDirectory($src);

        return (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output', $output), rootSources: new RootSources($domains, realpath($src) ?: $src), includes: $includes))->emit(
            (new PhpDocTypeCollector(domainGuesser: $domains))->collect($src),
            $responses->collect($src),
            (new EndpointContractCollector(domainGuesser: $domains))->collect($src, $responses->collectIndex($src)),
        );
    }
}
