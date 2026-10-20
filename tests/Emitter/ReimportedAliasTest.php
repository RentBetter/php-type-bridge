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

    /**
     * @return array<string, string>
     */
    private function emit(): array
    {
        $responses = new ResponseClassCollector();
        $enums = new EnumResolver();
        $enums->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output')))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responses->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responses->collectIndex(self::SRC)),
        );
    }
}
