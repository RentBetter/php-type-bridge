<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
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
     * @return array<string, string>
     */
    private function emit(string $src): array
    {
        $responses = new ResponseClassCollector();
        $enums = new EnumResolver();
        $enums->scanDirectory($src);

        return (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output')))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responses->collect($src),
            (new EndpointContractCollector())->collect($src, $responses->collectIndex($src)),
        );
    }
}
