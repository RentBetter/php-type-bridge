<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Tests\Fixture\FixtureProject;

final class PhpDocTypeCollectorTest extends TestCase
{
    public function test_it_collects_self_types_and_resolves_imports(): void
    {
        $domains = (new PhpDocTypeCollector())->collect(FixtureProject::srcDir());

        self::assertArrayHasKey('Common', $domains);
        self::assertArrayHasKey('Projects', $domains);
        self::assertArrayHasKey('TimestampedView', $domains['Common']->types);
        self::assertArrayHasKey('ProjectView', $domains['Projects']->types);

        $projectView = $domains['Projects']->types['ProjectView'];
        self::assertInstanceOf(IntersectionType::class, $projectView->parsed);
        // The name it is imported as; the import says which type that is.
        self::assertSame('TimestampedData', $projectView->parsed->base->name);
        self::assertSame('TimestampedView', self::importedAs('TimestampedData', $projectView->imports)?->targetTypeName);

        $fields = [];
        foreach ($projectView->parsed->extra->fields as $field) {
            $fields[$field->name] = $field;
        }

        self::assertInstanceOf(NameRefType::class, $fields['client']->type);
        self::assertSame('ClientData', $fields['client']->type->name);
        self::assertSame('ClientView', self::importedAs('ClientData', $projectView->imports)?->targetTypeName);
        self::assertInstanceOf(ValueOfType::class, $fields['status']->type);
        self::assertSame('ProjectStatus', $fields['status']->type->enumClass);
        self::assertTrue($fields['nickname']->optional);
    }

    public function test_it_collects_multiple_projections_extending_a_shared_base_shape(): void
    {
        $domains = (new PhpDocTypeCollector())->collect(FixtureProject::srcDir());

        self::assertArrayHasKey('ProjectBaseView', $domains['Projects']->types);
        self::assertArrayHasKey('ProjectOwnerView', $domains['Projects']->types);
        self::assertArrayHasKey('ProjectAdminView', $domains['Projects']->types);
        self::assertArrayHasKey('ProjectStatusData', $domains['Projects']->types);

        $ownerView = $domains['Projects']->types['ProjectOwnerView'];
        self::assertInstanceOf(IntersectionType::class, $ownerView->parsed);
        self::assertSame('ProjectBaseData', $ownerView->parsed->base->name);
        self::assertSame('ProjectBaseView', self::importedAs('ProjectBaseData', $ownerView->imports)?->targetTypeName);

        $ownerFields = [];
        foreach ($ownerView->parsed->extra->fields as $field) {
            $ownerFields[$field->name] = $field;
        }

        self::assertInstanceOf(ScalarType::class, $ownerFields['canEdit']->type);
        self::assertSame('bool', $ownerFields['canEdit']->type->type);
        self::assertInstanceOf(NullableType::class, $ownerFields['ownerNotes']->type);
        self::assertInstanceOf(ScalarType::class, $ownerFields['ownerNotes']->type->inner);
        self::assertSame('string', $ownerFields['ownerNotes']->type->inner->type);

        $adminView = $domains['Projects']->types['ProjectAdminView'];
        self::assertInstanceOf(IntersectionType::class, $adminView->parsed);
        self::assertSame('ProjectBaseData', $adminView->parsed->base->name);
        self::assertSame('ProjectBaseView', self::importedAs('ProjectBaseData', $adminView->imports)?->targetTypeName);

        $adminFields = [];
        foreach ($adminView->parsed->extra->fields as $field) {
            $adminFields[$field->name] = $field;
        }

        self::assertInstanceOf(ListType::class, $adminFields['auditTrail']->type);
        self::assertInstanceOf(ScalarType::class, $adminFields['auditTrail']->type->inner);
        self::assertSame('string', $adminFields['auditTrail']->type->inner->type);
        self::assertInstanceOf(NameRefType::class, $adminFields['statusDetail']->type);
        self::assertSame('ProjectStatusData', $adminFields['statusDetail']->type->name);

        $statusData = $domains['Projects']->types['ProjectStatusData'];
        $statusFields = [];
        foreach ($statusData->parsed->fields as $field) {
            $statusFields[$field->name] = $field;
        }

        self::assertInstanceOf(ValueOfType::class, $statusFields['value']->type);
        self::assertSame('ProjectStatus', $statusFields['value']->type->enumClass);
    }

    public function test_an_alias_typed_with_an_import_from_outside_the_tree_says_which(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not be resolved/');

        (new PhpDocTypeCollector())->collect(__DIR__ . '/../Fixture/UnresolvedImportUsedFixtures');
    }

    public function test_an_unresolved_imports_name_spelt_as_a_key_or_a_literal_is_no_use_of_it(): void
    {
        $domains = (new PhpDocTypeCollector())->collect(__DIR__ . '/../Fixture/UnresolvedImportSpelledFixtures');

        self::assertSame(['LedgerView'], array_keys($domains['Common']->types));
    }

    public function test_an_import_from_outside_the_tree_that_no_alias_uses_is_no_matter(): void
    {
        $domains = (new PhpDocTypeCollector())->collect(__DIR__ . '/../Fixture/InheritedAliasAndOutOfTreeImportFixtures');

        self::assertSame(['Page'], array_keys($domains['Common']->types));
    }

    public function test_an_import_of_an_alias_kept_for_php_stan_is_none_of_what_is_emitted(): void
    {
        // LineView imports RowSource's Row for its own code, and emits a Line that never names it:
        // the import is no symbol of any module, so it is none of Line's.
        $domains = (new PhpDocTypeCollector())->collect(__DIR__ . '/../Fixture/PhpStanOnlyUnusedImportFixtures');

        self::assertSame([], $domains['Notes']->types['Line']->imports);
    }

    public function test_an_alias_typed_with_an_imported_php_stan_only_alias_says_so(): void
    {
        // RowSource keeps `Row` for PHPStan; RowView emits a `Row` of its own. LineView imports
        // RowSource's, which is never emitted — it is not quietly RowView's instead.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/"Row" is #\\[PhpStanOnly\\] on .*RowSource.*Line on .*LineView/s');

        (new PhpDocTypeCollector())->collect(__DIR__ . '/../Fixture/PhpStanOnlyImportedFixtures');
    }

    /**
     * @param list<ImportedType> $imports
     */
    private static function importedAs(string $name, array $imports): ?ImportedType
    {
        foreach ($imports as $import) {
            if ($name === $import->localAlias) {
                return $import;
            }
        }

        return null;
    }
}
