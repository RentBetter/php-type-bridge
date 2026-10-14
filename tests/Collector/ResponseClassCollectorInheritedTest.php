<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response\ConflictResponse;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response\NotFoundResponse;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Notes\Response\ListNotesResponse;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Notes\Response\NoteGoneResponse;

/**
 * A property a response inherits is on the wire — TypeBridgeResponseSubscriber and the throwable
 * listener write every public property — so it is published, typed as the file that declares it
 * says. An error response that extends a base class holding the `{error: …}` envelope generated
 * as an empty interface before.
 */
final class ResponseClassCollectorInheritedTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/InheritedResponseFixtures';

    public function test_an_inherited_property_is_collected_with_the_type_its_declaring_file_gives_it(): void
    {
        $index = (new ResponseClassCollector())->collectIndex(self::SRC);

        self::assertSame(['error' => 'ErrorBodyData'], self::rawTypes($index[NotFoundResponse::class]));
        self::assertSame(['error' => 'ErrorBodyData'], self::rawTypes($index[NoteGoneResponse::class]), 'From a base class in another domain');
        self::assertSame(['error' => 'ErrorBodyData'], self::rawTypes($index[ConflictResponse::class]), 'Redeclared, it is still one property');
        self::assertSame(['notes' => 'list<string>', 'total' => 'int<0, max>'], self::rawTypes($index[ListNotesResponse::class]), 'Its own, then what it inherits');
    }

    public function test_an_inherited_property_carries_the_imports_of_the_file_that_declares_it(): void
    {
        $response = (new ResponseClassCollector())->collectIndex(self::SRC)[NoteGoneResponse::class];

        self::assertSame([], $response->imports, 'The response\'s own file imports nothing');
        $imports = $response->properties[0]->imports;
        self::assertNotNull($imports);
        self::assertSame(['ErrorBodyData'], array_map(static fn(object $import): string => $import->localAlias, $imports));
        self::assertSame('Common', $imports[0]->targetDomain);

        $own = (new ResponseClassCollector())->collectIndex(self::SRC)[ListNotesResponse::class]->properties[0];
        self::assertSame('notes', $own->name);
        self::assertSame([], $own->imports, 'Its own property is read against the response\'s file, which imports nothing');
    }

    public function test_it_emits_the_envelope_on_every_error_response_and_imports_it_across_domains(): void
    {
        $output = self::emit();

        self::assertStringContainsString("export interface NotFoundResponse {\n  error: ErrorBody;\n}", $output['Common']);
        self::assertStringContainsString("export interface ConflictResponse {\n  error: ErrorBody;\n}", $output['Common']);
        self::assertStringContainsString("export interface NoteGoneResponse {\n  error: ErrorBody;\n}", $output['Notes']);
        self::assertMatchesRegularExpression('/import type \{[^}]*\bErrorBody\b[^}]*\} from \'[^\']*Common[^\']*\';/i', $output['Notes']);
        self::assertStringContainsString("export interface ListNotesResponse {\n  notes: string[];\n  total: number;\n}", $output['Notes']);
    }

    public function test_a_base_classs_import_types_only_its_own_property_though_the_child_imports_a_shape_of_the_same_name(): void
    {
        // Both files import a shape the emitter calls `Meta`: the child's own, and the base's from
        // another domain. Each property is read in the file that declares it.
        $notes = self::emit(self::fixtures('InheritedSameNameFixtures'))['Notes'];

        self::assertStringContainsString("export interface ListNotesResponse {\n  meta: Meta;\n  page: CommonMeta;\n}", $notes);
        self::assertStringContainsString("export interface Meta {\n  author: string;\n}", $notes);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bMeta as CommonMeta\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
    }

    public function test_an_alias_the_base_class_declares_itself_is_imported_where_the_child_is_emitted(): void
    {
        $output = self::emit(self::fixtures('InheritedLocalAliasFixtures'));

        self::assertStringContainsString("export interface NoteGoneResponse {\n  error: Envelope;\n}", $output['Notes']);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bEnvelope\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $output['Notes']);
        self::assertStringContainsString("export interface Envelope {\n  code: string;\n  message: string;\n}", $output['Common']);
    }

    public function test_the_same_alias_in_the_base_and_the_child_names_each_files_own_shape(): void
    {
        $notes = self::emit(self::fixtures('InheritedAliasClashFixtures'))['Notes'];

        self::assertStringContainsString("export interface NoteRejectedResponse {\n  note: NoteBody;\n  error: ErrorBody;\n}", $notes);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bErrorBody\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
    }

    public function test_a_base_classs_import_from_outside_the_source_tree_is_no_matter_when_no_published_property_uses_it(): void
    {
        $output = self::emit(self::fixtures('InheritedOutOfTreeImportFixtures'));

        self::assertStringContainsString("export interface ListAuditedNotesResponse {\n  total: number;\n}", $output['Notes']);
    }

    public function test_a_published_property_typed_with_an_import_from_outside_the_source_tree_says_so(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not be resolved\\..*ListAuditedNotesResponse property .*AbstractAuditedResponse::\\$audit is typed with it/');

        (new ResponseClassCollector())->collectIndex(self::fixtures('InheritedOutOfTreeImportUsedFixtures'));
    }

    public function test_a_response_that_is_also_a_base_types_its_children_the_same_whichever_is_read_first(): void
    {
        // NotFoundResponse is read as a response of its own and as the base of another: read before
        // its child (Notes) in one tree, after it (Alerts) in the other. Its alias means the same.
        foreach (['InheritedConcreteBaseFixtures' => ['Notes', 'NoteNotFoundResponse'], 'InheritedConcreteBaseReadLastFixtures' => ['Alerts', 'AlertNotFoundResponse']] as $fixtures => [$domain, $response]) {
            $output = self::emit(self::fixtures($fixtures));

            self::assertStringContainsString("export interface NotFoundResponse {\n  error: Envelope;\n}", $output['Common'], $fixtures);
            self::assertStringContainsString("export interface {$response} {\n  error: Envelope;\n}", $output[$domain], $fixtures);
            self::assertMatchesRegularExpression('/import type \\{[^}]*\\bEnvelope\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $output[$domain], $fixtures);
        }
    }

    public function test_a_base_with_aliases_of_its_own_tolerates_an_import_no_published_type_uses(): void
    {
        // PhpDocTypeCollector reads a file for its aliases as much as this collector does for its
        // properties: an import from outside the tree that only a protected property uses fails
        // neither.
        $output = self::emit(self::fixtures('InheritedAliasAndOutOfTreeImportFixtures'));

        self::assertStringContainsString("export interface ListNotesResponse {\n  page: Page;\n}", $output['Notes']);
        self::assertStringContainsString("export interface Page {\n  total: number;\n}", $output['Common']);
    }

    public function test_an_inherited_class_name_is_the_classs_type_though_the_childs_module_has_an_alias_of_that_name(): void
    {
        $notes = self::emit(self::fixtures('InheritedClassNameFixtures'))['Notes'];

        self::assertStringContainsString("export interface ShowTotalResponse {\n  total: CommonMoney;\n}", $notes);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bMoney as CommonMoney\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
    }

    public function test_a_204_has_no_properties_whatever_it_inherits(): void
    {
        // TypeBridgeResponseSubscriber sends a 204 with no body at all, so nothing it declares or
        // inherits is published: not a property, not an enum, not a prefixed name.
        $src = self::fixtures('Inherited204Fixtures');
        self::assertSame([], (new ResponseClassCollector())->collectIndex($src)['PTGS\\TypeBridge\\Tests\\Fixture\\Inherited204Fixtures\\Notes\\Response\\DeleteNoteResponse']->properties);

        $notes = self::emit($src, new TypeScriptNaming(interfacePrefix: 'I'))['Notes'];

        self::assertStringContainsString('export type DeleteNoteResponse = null;', $notes);
        self::assertStringContainsString('export type ArchiveNoteResponse = null;', $notes);
        self::assertStringNotContainsString('NoteState', $notes);
    }

    public function test_a_php_stan_only_alias_is_neither_emitted_nor_imported(): void
    {
        $notes = self::emit(self::fixtures('PhpStanOnlyResponseFixtures'))['Notes'];

        self::assertStringContainsString("export interface ShowNoteResponse {\n  title: string;\n}", $notes);
        self::assertStringNotContainsString('Working', $notes);
        self::assertStringNotContainsString('Draft', $notes);
    }

    public function test_a_module_alias_still_wins_over_a_class_of_the_same_name_without_a_shape(): void
    {
        // As it always has for a response's own property; and an inherited one naming a class with
        // no `_self` falls back to it rather than failing.
        $notes = self::emit(self::fixtures('OwnClassAliasFixtures'))['Notes'];

        self::assertStringContainsString("export interface ShowNoteResponse {\n  summary: Summary;\n}", $notes);
        self::assertStringContainsString("export interface ShowDigestResponse {\n  digest: Digest;\n}", $notes);
    }

    public function test_a_property_a_trait_declares_is_read_in_the_traits_file(): void
    {
        $notes = self::emit(self::fixtures('InheritedTraitFixtures'))['Notes'];

        self::assertStringContainsString("export interface NoteGoneResponse {\n  error: ErrorBody;\n}", $notes);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bErrorBody\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
    }

    public function test_a_source_tree_is_read_once_however_often_it_is_asked_for(): void
    {
        $collector = new ResponseClassCollector();
        $first = $collector->collectIndex(self::SRC);

        self::assertSame($first, $collector->collectIndex(self::SRC));
        self::assertSame($first[NotFoundResponse::class], $collector->collect(self::SRC)['Common'][array_search(NotFoundResponse::class, array_map(static fn(CollectedApiResponseClass $response): string => $response->className, $collector->collect(self::SRC)['Common']), true)]);
    }

    /**
     * @return array<string, string>
     */
    private static function rawTypes(CollectedApiResponseClass $response): array
    {
        return array_combine(
            array_map(static fn(CollectedResponseProperty $property): string => $property->name, $response->properties),
            array_map(static fn(CollectedResponseProperty $property): string => $property->rawType, $response->properties),
        );
    }

    private static function fixtures(string $directory): string
    {
        return __DIR__ . '/../Fixture/' . $directory;
    }

    /**
     * @return array<string, string>
     */
    private static function emit(string $src = self::SRC, ?TypeScriptNaming $naming = null): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            naming: $naming ?? new TypeScriptNaming(),
        ))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responseCollector->collect($src),
            (new EndpointContractCollector())->collect($src, $responseCollector->collectIndex($src)),
        );
    }
}
