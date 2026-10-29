<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\ReimportCycleFixtures\Common\E as CycleE;
use PTGS\TypeBridge\Tests\Fixture\ReimportCycleFixtures\Notes\A as CycleA;
use PTGS\TypeBridge\Tests\Fixture\ReimportCycleFixtures\Notes\B as CycleB;
use PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Common\D;
use PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Notes\A;
use PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Notes\B;

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

    public function testAnImportIsTheSameWhicheverClassIsReadFirst(): void
    {
        // A is read first; following its `Foo` reads B, which imports `Bar` back from A while A is
        // still being followed. B's `Bar` is still D's, as it is when B is read on its own.
        $src = __DIR__ . '/../Fixture/ReimportOrderFixtures';
        $files = new ClassFileTypes();
        $classes = $files->classesIn($src);
        $files->imports(A::class, $classes[A::class], $src, $classes);
        self::assertSame(D::class, $files->imports(B::class, $classes[B::class], $src, $classes)['imports']['Bar']->targetClass);

        $notes = $this->emit($src)['Notes'];
        self::assertStringContainsString("export interface BUses {\n  bar: Bar;\n}", $notes);
        self::assertStringContainsString("export interface Uses {\n  foo: Foo;\n  bar: Bar;\n}", $notes);
    }

    public function testAClassTheAutoloaderCantFindStillEmitsItsTypes(): void
    {
        // Its namespace doesn't match its path: nothing throws loading it, it just isn't found, and
        // its types are read from its file as they always were. Only one whose declaration throws is
        // no part of the application.
        $notes = $this->emit(__DIR__ . '/../Fixture/MisplacedShapesFixtures')['Notes'] ?? '';

        self::assertStringContainsString("export interface NoteFilter {\n  status: string;\n}", $notes);
    }

    public function testAnImportThroughAClassBeingFollowedIsTheSameWhicheverIsReadFirst(): void
    {
        // A's `X` is B's, which B imports back from A as `V`: A's own import of `V`, from D, which has
        // it from E. Followed alias by alias, it is E's however the two are first read.
        $src = __DIR__ . '/../Fixture/ReimportCycleFixtures';
        foreach ([[CycleA::class, CycleB::class], [CycleB::class, CycleA::class]] as $order) {
            $files = new ClassFileTypes();
            $classes = $files->classesIn($src);
            foreach ($order as $class) {
                $files->imports($class, $classes[$class], $src, $classes);
            }

            self::assertSame(CycleE::class, $files->imports(CycleA::class, $classes[CycleA::class], $src, $classes)['imports']['X']->targetClass, implode(' then ', $order));
            self::assertSame(CycleE::class, $files->imports(CycleB::class, $classes[CycleB::class], $src, $classes)['imports']['X']->targetClass, implode(' then ', $order));
        }
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
