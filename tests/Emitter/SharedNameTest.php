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
 * The names every module may share — the helpers, the config aliases — against the names a module
 * takes itself: a type of its own of a helper's name fails, naming both, whichever helper; an
 * endpoint's `const` of one is declared beside it; and an import is given a name TypeScript reads.
 */
final class SharedNameTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../Fixture/';

    public function test_an_endpoint_whose_result_takes_a_helpers_name_is_a_collision(): void
    {
        // The endpoint `Endpoint`'s result is `EndpointResult`, which the module declares as the
        // helper too: two declarations, one name.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "Notes": "EndpointResult" is emitted by both the endpoint helper EndpointResult and the result of endpoint Endpoint.');

        $this->emit('HelperNamedEndpointFixtures');
    }

    public function test_a_root_endpoints_query_named_as_a_helper_only_another_module_takes_is_a_collision(): void
    {
        // The root module's `Include` takes a query form, declared `IncludeQuery`; Notes' endpoint
        // takes the include query, whose helper is declared in the root module too.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "": "IncludeQuery" is emitted by both the query of endpoint Include and the root module\'s endpoint helper IncludeQuery.');

        $this->emit('RootQueryNamedFixtures', new OutputStructure(rootModule: 'genTypes.ts'), ['Shared'], new IncludeConvention(query: ['include' => 'Opt-in parts.']));
    }

    public function test_an_endpoint_named_as_what_the_module_imports_from_the_root_module_declares_it_instead(): void
    {
        // An import may not share a name with the module's `const`: what the module takes of that
        // name is declared in it, as it is without a root module, rather than imported.
        $notes = $this->emit('SharedNamedEndpointFixtures', new OutputStructure(rootModule: 'genTypes.ts'), typeAliases: ['Money' => 'string'])['Notes'] ?? '';

        self::assertStringContainsString('export const Money:', $notes);
        self::assertStringContainsString('export type Money = string;', $notes);
        self::assertStringContainsString('export const EndpointResult:', $notes);
        self::assertStringContainsString('export type EndpointResult<M extends Record<number, unknown>> =', $notes);
        self::assertMatchesRegularExpression("~^import type \\{ Endpoint \\} from '\\.\\./genTypes';$~m", $notes);
    }

    public function test_without_a_root_module_an_endpoint_named_as_a_shared_name_is_declared_beside_it(): void
    {
        $notes = $this->emit('SharedNamedEndpointFixtures', typeAliases: ['Money' => 'string'])['Notes'] ?? '';

        self::assertStringContainsString('export type Money = string;', $notes);
        self::assertStringNotContainsString('import type', $notes);
    }

    public function test_an_import_from_a_module_two_levels_deep_is_named_as_typescript_reads_it(): void
    {
        // The module `Admin/Notes` declares a `Meta` of its own; `Admin/Checks`' is imported under
        // a name of letters, not of the module's path.
        $notes = $this->emit('DeepAliasFixtures', new OutputStructure(domainDepth: 2), depth: 2)['Admin/Notes'] ?? '';

        self::assertMatchesRegularExpression("~^import type \\{ Meta as AdminChecksMeta \\} from '\\.\\./Checks/genTypes';$~m", $notes);
        self::assertStringContainsString('theirs: AdminChecksMeta;', $notes);
    }

    public function test_a_type_named_as_the_ref_helper_is_a_collision(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "Checks": "Ref" is emitted by both the expanding helper Ref and type ');

        $this->emit('RefNamedTypeFixtures', includes: new IncludeConvention(refTypes: ['ref']));
    }

    public function test_with_a_root_module_a_type_named_as_the_ref_it_imports_is_a_collision(): void
    {
        // The module imports `Ref` from the root module, where the helper is declared.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "Checks": "Ref" is emitted by both the expanding helper Ref the module imports from the root module and type ');

        $this->emit('RefNamedTypeFixtures', new OutputStructure(rootModule: 'genTypes.ts'), includes: new IncludeConvention(refTypes: ['ref']));
    }

    public function test_a_type_named_as_a_helper_the_expanding_helpers_declare_is_a_collision(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "Checks": "ListItem" is emitted by both the expanding helper ListItem and type ');

        $this->emit('ListItemNamedTypeFixtures', includes: new IncludeConvention(refTypes: ['ref']));
    }

    public function test_with_a_root_module_a_module_may_name_a_type_as_a_helper_it_does_not_import(): void
    {
        // The expanding helpers are declared in the root module; the module imports only `Ref`.
        $checks = $this->emit('ListItemNamedTypeFixtures', new OutputStructure(rootModule: 'genTypes.ts'), includes: new IncludeConvention(refTypes: ['ref']))['Checks'] ?? '';

        self::assertStringContainsString('export interface ListItem {', $checks);
        self::assertStringContainsString("import type { Ref } from '../genTypes';", $checks);
    }

    public function test_a_root_source_named_as_a_helper_the_expanding_helpers_declare_is_a_collision_in_the_root_module(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "": "ListItem" is emitted by both the root module\'s expanding helper ListItem and type ');

        $this->emit('ListItemNamedTypeFixtures', new OutputStructure(rootModule: 'genTypes.ts'), ['Checks'], new IncludeConvention(refTypes: ['ref']));
    }

    public function test_a_type_named_as_a_helper_the_including_helpers_declare_is_a_collision(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "Notes": "IncludePath" is emitted by both the including helper IncludePath and type ');

        $this->emit('IncludePathNamedTypeFixtures', includes: new IncludeConvention(['included']));
    }

    /**
     * @param list<string> $rootSources
     * @param array<string, string> $typeAliases
     *
     * @return array<string, string>
     */
    private function emit(string $fixtures, OutputStructure $output = new OutputStructure(), array $rootSources = [], IncludeConvention $includes = new IncludeConvention(), array $typeAliases = [], int $depth = 1): array
    {
        $src = self::FIXTURES . $fixtures;
        $domains = new DomainGuesser($depth, $rootSources);
        $responses = new ResponseClassCollector(domainGuesser: $domains);
        $enums = new EnumResolver(domainGuesser: $domains);
        $enums->scanDirectory($src);

        return (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output', $output), typeAliases: $typeAliases, rootSources: new RootSources($domains, realpath($src) ?: $src), includes: $includes))->emit(
            (new PhpDocTypeCollector(domainGuesser: $domains))->collect($src),
            $responses->collect($src),
            (new EndpointContractCollector(domainGuesser: $domains))->collect($src, $responses->collectIndex($src)),
        );
    }
}
