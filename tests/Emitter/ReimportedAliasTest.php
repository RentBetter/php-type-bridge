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

/**
 * An alias imported from a class that only imports it itself is the type where it is declared, as
 * PHPStan reads it: ReportScopes re-imports ProductScopeData's `_self` as `Scope`, and OwnerData's,
 * in another module, as `Owner`. What imports either from ReportScopes is typed with that type.
 */
final class ReimportedAliasTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/ReimportedScopeFixtures';

    public function testAnAliasReimportedInTheSameModuleIsTheTypeItIsDeclaredAs(): void
    {
        self::assertStringContainsString('export interface ScopedReportFilterData extends ProductScopeData {', $this->emit()['Reports']);
    }

    public function testAnAliasReimportedFromAnotherModuleIsImportedFromThatOne(): void
    {
        $reports = $this->emit()['Reports'];

        self::assertMatchesRegularExpression('~^import type \{ OwnerData \} from \'[^\']*Common[^\']*\';$~m', $reports);
        self::assertStringContainsString("export interface ReportOwnerResponse {\n  owner: OwnerData;\n}", $reports);
    }

    public function testAnAliasReimportedFromOutsideTheSourceTreeSaysWhyWhereItIsUsed(): void
    {
        // NoteItems re-imports a library's `_self`; NoteData imports it from there. Why NoteItems' import
        // can't be emitted is why NoteData's can't.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Class reference "Item" from "[^"]*NoteItems" could not be resolved\. The @phpstan-type _self on [^ ]*NoteData is typed with it\./');

        $this->emit(__DIR__ . '/../Fixture/ReimportedLibraryFixtures');
    }

    public function testImportsThatLeadBackToEachOtherFailAsAnAliasNeitherDeclares(): void
    {
        // NoteData imports `Code` from NoteCodes, which imports it back: followed once round, not forever.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown TypeScript symbol "Code"');

        $this->emit(__DIR__ . '/../Fixture/CyclicImportFixtures');
    }

    /**
     * @return array<string, string>
     */
    private function emit(string $src = self::SRC): array
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
