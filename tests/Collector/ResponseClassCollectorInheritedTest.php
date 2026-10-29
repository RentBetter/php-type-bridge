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
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractMetaResponse;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\AbstractOwnedResponse;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib\Item as LibraryItem;
use PTGS\TypeBridge\Tests\Fixture\LibraryBaseOwnedFixtures\Notes\Response\ShowNoteResponse as ShowOwnedNoteResponse;
use PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Common\Response\NoteSummary;
use PTGS\TypeBridge\Tests\Fixture\UnresolvedImportSpelledFixtures\Common\Response\LedgerResponse;
use RuntimeException;

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

    public function test_a_base_classs_import_is_used_under_the_name_it_is_imported_as_whatever_the_prefix(): void
    {
        // With an interface prefix both are emitted `IMeta`: the inherited one is the imported one.
        $notes = self::emit(self::fixtures('InheritedSameNameFixtures'), new TypeScriptNaming(interfacePrefix: 'I'))['Notes'];

        self::assertStringContainsString("export interface IListNotesResponse {\n  meta: IMeta;\n  page: CommonIMeta;\n}", $notes);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bIMeta as CommonIMeta\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
    }

    public function test_an_enum_imported_under_another_name_is_used_under_it(): void
    {
        // The Notes module declares a `NoteState` of its own; the Common enum a base names is
        // imported under another name, and the property is typed with that.
        $notes = self::emit(self::fixtures('EnumAliasClashFixtures'))['Notes'];

        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bNoteState as CommonNoteState\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
        self::assertStringContainsString("export interface ShowNoteResponse {\n  label: NoteState;\n  state: CommonNoteState;\n}", $notes);
    }

    public function test_a_generic_bases_params_are_what_the_response_binds_them_to(): void
    {
        // `@extends AbstractListResponse<NoteView>`, the alias the child's own file declares, in the
        // child's module; through a base that passes its own param on and binds the other; unbound,
        // each param its bound or anything; a library's generic base too.
        $notes = self::emit(self::fixtures('GenericBaseFixtures'))['Notes'];

        self::assertStringContainsString("export interface ListNotesResponse {\n  items: NoteView[];\n  first: NoteView | null;\n  labels: string[];\n}", $notes);
        self::assertStringContainsString("export interface ListPinnedNotesResponse {\n  items: number[];\n  first: number | null;\n  labels: ('pinned' | 'archived')[];\n}", $notes);
        self::assertStringContainsString("export interface ListAnythingResponse {\n  items: unknown[];\n  first: unknown | null;\n  labels: string[];\n}", $notes);
        self::assertStringContainsString("export interface NotePageResponse {\n  rows: number[];\n}", $notes);
        // `@phpstan-template`, `@template-covariant`, and `@phpstan-extends` over `@extends`.
        self::assertStringContainsString("export interface NotePairResponse {\n  key: string;\n  value: boolean;\n}", $notes);
    }

    public function test_a_generic_bases_binding_is_read_in_the_module_of_the_file_that_writes_it(): void
    {
        // The binding names the Notes class Note, and NoteSummary, an alias of the Notes module: the
        // Common module's own `Note` is no part of it.
        $notes = self::emit(self::fixtures('GenericBaseFixtures'))['Notes'];

        self::assertStringContainsString("export interface ListModelNotesResponse {\n  items: Note[];\n  first: Note | null;\n", $notes);
        self::assertStringContainsString("export interface ListSummariesResponse {\n  items: NoteSummary[];\n  first: NoteSummary | null;\n", $notes);
        self::assertStringNotContainsString('CommonNote', $notes, "Neither Common's Note, nor the NoteView the base's file imports for none of its properties");
        // A type the binding's file imports from another module is imported from there.
        self::assertStringContainsString("export interface ListTaggedNotesResponse {\n  items: Tag[];\n", $notes);
        self::assertMatchesRegularExpression('/^import type \\{[^}]*\\bTag\\b[^}]*\\} from \'[^\']*Common[^\']*\';$/m', $notes);
    }

    public function test_a_param_bound_to_a_type_left_out_when_null_is_that(): void
    {
        // `@extends AbstractBoxResponse<?NoteView>`: `item` is `?NoteView`, a key left out when null;
        // in a list, an item that may be null.
        $notes = self::emit(self::fixtures('GenericBaseFixtures'))['Notes'];

        self::assertStringContainsString("export interface BoxedNoteResponse {\n  item?: NoteView;\n  items: (NoteView | null)[];\n}", $notes);
    }

    public function test_a_template_param_is_read_as_phpstan_ranks_its_spellings(): void
    {
        // `@phpstan-template R of int` over `@template R`; `@psalm-template P of string`, read too;
        // `@template D = bool`, its default where nothing binds it.
        $notes = self::emit(self::fixtures('GenericBaseFixtures'))['Notes'];

        self::assertStringContainsString("export interface RankedNotesResponse {\n  ranks: number[];\n  tags: string[];\n  flag: boolean;\n}", $notes);
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

    public function test_an_alias_of_its_own_and_an_import_named_alike_are_each_the_type_the_file_means(): void
    {
        // The response declares a `Meta` and imports Common's as `CommonMeta`: two types. Each is
        // named as the file names it — in a property and in an alias it declares alike — though
        // both are emitted `Meta`, one in each module.
        $notes = self::emit(self::fixtures('SameNamedAliasFixtures'))['Notes'];

        self::assertStringContainsString("export interface NoteResponse {\n  meta: Meta;\n  common: CommonMeta;\n  pair: Pair;\n}", $notes);
        self::assertStringContainsString("export interface Pair {\n  own: Meta;\n  common: CommonMeta;\n}", $notes);
        self::assertStringContainsString("export interface Meta {\n  page: number;\n}", $notes);
        self::assertMatchesRegularExpression("/import type \\{[^}]*\\bMeta as CommonMeta\\b[^}]*\\} from '[^']*Common[^']*';/", $notes);
    }

    public function test_an_import_no_published_property_names_is_no_matter_however_it_resolves(): void
    {
        // The base imports `Working` from Relay, which only imports it itself, for a property that is
        // never sent. Each published property is converted against the imports it names.
        $notes = self::emit(self::fixtures('UnusedBaseImportFixtures'))['Notes'];

        self::assertStringContainsString("export interface ShowNoteResponse {\n  title: string;\n}", $notes);
    }

    public function test_a_property_of_a_base_with_no_file_is_published_as_declared(): void
    {
        // Declared at run time, as eval or a PHP extension declares a class: there is no file to
        // read its names in, but its public properties are sent, so they are published — typed as
        // their declarations say, a name meaning the class it spells in full.
        if (!class_exists('PTGS\\TypeBridge\\Tests\\Fixture\\FilelessBaseFixtures\\FilelessBase', false)) {
            eval('namespace PTGS\\TypeBridge\\Tests\\Fixture\\FilelessBaseFixtures; abstract class FilelessBase { public int $count = 0; /** @var list<string> */ public array $tags = []; }');
        }

        $notes = self::emit(self::fixtures('FilelessBaseFixtures'))['Notes'];

        self::assertStringContainsString("export interface ShowNoteResponse {\n  title: string;\n  count: number;\n  tags: string[];\n}", $notes);
    }

    public function test_a_library_propertys_enum_is_the_one_its_file_names_not_one_of_the_same_short_name(): void
    {
        // The library's `value-of<Status>` is its own Status, outside the source tree and never
        // emitted — not the application's Status that shares its short name.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/LibraryBaseLib\\\\Status.*outside the source tree/s');

        self::emit(self::fixtures('LibraryEnumFixtures'));
    }

    public function test_a_config_alias_on_a_library_property_is_the_emitting_modules_own(): void
    {
        // PHPStan reads a project-wide alias in any file, a library's included.
        $src = self::fixtures('LibraryConfigAliasFixtures');
        $responseCollector = new ResponseClassCollector(typeAliases: ['UuidStr' => 'string']);
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        $notes = (new TypeScriptEmitter(enumResolver: $enumResolver, domainMapper: new DomainMapper('/tmp/type-bridge-output'), typeAliases: ['UuidStr' => 'string']))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responseCollector->collect($src),
            [],
        )['Notes'];

        self::assertStringContainsString("export interface ShowNoteResponse {\n  ownerId: UuidStr;\n}", $notes);
        self::assertStringContainsString('export type UuidStr = string;', $notes);
    }

    public function test_an_enum_a_property_inherited_from_another_module_names_is_emitted_in_its_own(): void
    {
        // Nothing in Common names NoteState but the abstract base's property, published in Notes:
        // Common is written with it, for Notes to import.
        $output = self::emit(self::fixtures('CrossModuleEnumFixtures'));

        self::assertArrayHasKey('Common', $output);
        self::assertStringContainsString("export type NoteState = 'open' | 'closed';", $output['Common']);
        self::assertMatchesRegularExpression("/import type \\{[^}]*\\bNoteState\\b[^}]*\\} from '[^']*Common[^']*';/", $output['Notes']);
    }

    public function test_a_204_says_whether_it_declares_properties_of_its_own(): void
    {
        // What it was emitted as before it was null is the collected model's to say, not a question
        // for reflection at emit time.
        $index = (new ResponseClassCollector())->collectIndex(self::fixtures('Inherited204Fixtures'));

        self::assertTrue($index['PTGS\\TypeBridge\\Tests\\Fixture\\Inherited204Fixtures\\Notes\\Response\\RestoreNoteResponse']->declaresProperties);
        self::assertFalse($index['PTGS\\TypeBridge\\Tests\\Fixture\\Inherited204Fixtures\\Notes\\Response\\DeleteNoteResponse']->declaresProperties, 'It only inherits them');
    }

    public function test_the_name_a_204_keeps_is_what_its_collected_model_says(): void
    {
        // A model built as a project's own collector builds it, saying it declared properties: the
        // deprecated name is kept, whatever the class looks like when it is emitted.
        $src = self::fixtures('Inherited204Fixtures');
        $responseCollector = new ResponseClassCollector();
        $responses = $responseCollector->collect($src);
        $responses['Notes'] = array_map(static fn(CollectedApiResponseClass $response): CollectedApiResponseClass => 'DeleteNoteResponse' !== $response->name ? $response : new CollectedApiResponseClass(
            className: $response->className,
            name: $response->name,
            domain: $response->domain,
            sourceFile: $response->sourceFile,
            status: $response->status,
            error: $response->error,
            properties: [],
            imports: $response->imports,
            declaresProperties: true,
        ), $responses['Notes']);
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        $notes = (new TypeScriptEmitter(enumResolver: $enumResolver, domainMapper: new DomainMapper('/tmp/type-bridge-output'), naming: new TypeScriptNaming(interfacePrefix: 'I')))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responses,
            (new EndpointContractCollector())->collect($src, $responseCollector->collectIndex($src)),
        )['Notes'];

        self::assertMatchesRegularExpression('/\\/\\*\\* @deprecated [^*]*DeleteNoteResponse[^*]*\\*\\/\\nexport type IDeleteNoteResponse = DeleteNoteResponse;/', $notes);
    }

    public function test_a_base_classs_import_from_outside_the_source_tree_is_no_matter_when_no_published_property_uses_it(): void
    {
        $output = self::emit(self::fixtures('InheritedOutOfTreeImportFixtures'));

        self::assertStringContainsString("export interface ListAuditedNotesResponse {\n  total: number;\n}", $output['Notes']);
    }

    public function test_a_property_that_spells_an_unresolved_imports_name_as_a_key_or_a_literal_is_published(): void
    {
        $index = (new ResponseClassCollector())->collectIndex(self::fixtures('UnresolvedImportSpelledFixtures'));

        self::assertSame(['totals' => "array{Money: int, label: 'Money'}"], self::rawTypes($index[LedgerResponse::class]));
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

    public function test_a_204_that_declares_properties_keeps_the_name_it_had_as_a_deprecated_alias(): void
    {
        // It was an interface of what it declares, under the interface prefix; it is null now, under
        // its own name like every 204, and the old name still compiles for a while, deprecated.
        $notes = self::emit(self::fixtures('Inherited204Fixtures'), new TypeScriptNaming(interfacePrefix: 'I'))['Notes'];

        self::assertStringContainsString('export type RestoreNoteResponse = null;', $notes);
        self::assertMatchesRegularExpression('/\\/\\*\\* @deprecated [^*]*RestoreNoteResponse[^*]*\\*\\/\\nexport type IRestoreNoteResponse = RestoreNoteResponse;/', $notes);
        // One that only inherits them was never named so: nothing to keep.
        self::assertStringNotContainsString('IDeleteNoteResponse', $notes);
        self::assertStringNotContainsString('IArchiveNoteResponse', $notes);
        // Without a prefix the name never changed.
        self::assertStringNotContainsString('@deprecated', self::emit(self::fixtures('Inherited204Fixtures'))['Notes']);
    }

    public function test_a_204s_deprecated_name_is_one_no_other_type_of_its_module_may_take(): void
    {
        // It is declared beside the 204's own: another declaration of it is a collision, said so.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"IRestoreNoteResponse" is emitted by both');

        self::emit(self::fixtures('Deprecated204ClashFixtures'), new TypeScriptNaming(interfacePrefix: 'I'));
    }

    public function test_a_type_imported_under_a_204s_deprecated_name_is_imported_under_another(): void
    {
        $notes = self::emit(self::fixtures('Deprecated204ImportFixtures'), new TypeScriptNaming(interfacePrefix: 'I'))['Notes'];

        self::assertStringContainsString('import type { IRestoreNoteResponse as CommonIRestoreNoteResponse }', $notes);
        self::assertStringContainsString('  code: CommonIRestoreNoteResponse;', $notes);
        self::assertSame(1, substr_count($notes, 'export type IRestoreNoteResponse ='));
    }

    public function test_a_class_named_in_full_is_that_class_whatever_alias_its_file_imports_by_its_short_name(): void
    {
        // The file imports Common's `Money` alias and names Billing's Money class: each property is
        // the type it names, each imported, one under another name.
        $output = self::emit(self::fixtures('ClassAliasNameFixtures'));

        self::assertMatchesRegularExpression('/^import type \\{ Money \\} from \'[^\']*Billing[^\']*\';$/m', $output['Notes']);
        self::assertMatchesRegularExpression('/^import type \\{ Money as CommonMoney \\} from \'[^\']*Common[^\']*\';$/m', $output['Notes']);
        self::assertStringContainsString("export interface ShowPriceResponse {\n  price: Money;\n  total: CommonMoney;\n}", $output['Notes']);
        // Where the alias is used by nothing, the class is imported all the same.
        self::assertMatchesRegularExpression('/^import type \\{ Money \\} from \'[^\']*Billing[^\']*\';$/m', $output['Costs']);
        self::assertStringContainsString("export interface ShowCostResponse {\n  cost: Money;\n}", $output['Costs']);
    }

    public function test_two_enums_of_one_name_collide_in_a_module_of_enums_alone(): void
    {
        // Common holds only the two enums, named from Notes: it is written all the same, and two
        // declarations of `NoteState` in it are a collision, said so.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"NoteState" is emitted by both');

        self::emit(self::fixtures('EnumOnlyModuleFixtures'));
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
        // As it always has for a response's own property, and for one inherited from a base in the
        // same module: the name means there what it means in the module's own files.
        $notes = self::emit(self::fixtures('OwnClassAliasFixtures'))['Notes'];

        self::assertStringContainsString("export interface ShowNoteResponse {\n  summary: Summary;\n}", $notes);
        self::assertStringContainsString("export interface ShowSummaryResponse {\n  summary: Summary;\n}", $notes);
    }

    public function test_an_inherited_class_without_a_type_is_an_error_not_the_childs_module_alias(): void
    {
        // The base is written in Common, where `Digest` is the class: the Notes module's alias of
        // the same name is not what that file meant, whichever module the child is in.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('neither it nor a parent');

        self::emit(self::fixtures('InheritedClassNoTypeFixtures'));
    }

    public function test_an_inherited_name_is_an_alias_of_the_module_its_file_is_in(): void
    {
        // The base names Common's `Page` without importing it, as a module's own files may: in the
        // Notes module, which has a `Page` of its own, it is still Common's.
        $notes = self::emit(self::fixtures('InheritedModuleAliasFixtures'))['Notes'];

        self::assertStringContainsString("export interface ListNotesResponse {\n  page: CommonPage;\n}", $notes);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bPage as CommonPage\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
    }

    public function test_a_property_of_a_concrete_base_is_one_type_in_the_base_and_in_its_children(): void
    {
        // The base `use`s Billing's Money, and its module has an alias called Money: the alias wins,
        // as it always has for an own property — in the base's own interface and in the child's.
        $output = self::emit(self::fixtures('InheritedConcreteClassNameFixtures'));

        self::assertStringContainsString("export interface PriceResponse {\n  price: Money;\n}", $output['Common']);
        self::assertStringContainsString("export interface NotePriceResponse {\n  price: CommonMoney;\n}", $output['Notes']);
        self::assertStringNotContainsString('BillingMoney', $output['Notes']);
    }

    public function test_a_property_a_trait_declares_is_read_in_the_traits_file(): void
    {
        $notes = self::emit(self::fixtures('InheritedTraitFixtures'))['Notes'];

        self::assertStringContainsString("export interface NoteGoneResponse {\n  error: ErrorBody;\n}", $notes);
        self::assertMatchesRegularExpression('/import type \\{[^}]*\\bErrorBody\\b[^}]*\\} from \'[^\']*Common[^\']*\';/', $notes);
    }

    public function test_self_in_a_traits_property_is_the_class_using_it(): void
    {
        // As PHP and PHPStan read it: a trait's `self::` is whichever class uses the trait, so its
        // constants are looked up there — the trait's file gives only the names.
        $notes = self::emit(self::fixtures('TraitConstantFixtures'))['Notes'];

        self::assertMatchesRegularExpression('/export interface ShowNoteResponse \\{\\n  status: ([\'"])open\\1 \\| ([\'"])closed\\2;\\n\\}/', $notes);
    }

    public function test_a_property_a_library_trait_declares_is_published_as_it_was(): void
    {
        // The subscriber sends it whatever file it was written in; a trait outside the source tree
        // is read in the using class's file, as before traits were followed at all.
        $notes = self::emit(self::fixtures('LibraryTraitFixtures'))['Notes'];

        self::assertStringContainsString('  id: string;', $notes);
        self::assertStringContainsString('  meta: { page: number };', $notes);
    }

    public function test_a_property_a_library_base_class_declares_is_published(): void
    {
        // The subscriber sends it all the same, read in the library's file as PHPStan reads it.
        $src = self::fixtures('LibraryBaseFixtures');
        $response = (new ResponseClassCollector())->collectIndex($src)['PTGS\\TypeBridge\\Tests\\Fixture\\LibraryBaseFixtures\\Notes\\Response\\ShowNoteResponse'];

        self::assertSame(['id' => 'string', 'meta' => 'array{page: int}'], self::rawTypes($response));
        self::assertSame(AbstractMetaResponse::class, $response->properties[1]->writtenIn);
        self::assertStringContainsString('  meta: { page: number };', self::emit($src)['Notes']);
    }

    public function test_a_property_with_only_a_native_type_is_published_as_that_type_says(): void
    {
        // A base's, in the tree: `array` is a list or an object of anything, as json_encode writes
        // it; a nullable type keeps its null, the response's own included.
        $src = self::fixtures('NativeTypedFixtures');
        $response = (new ResponseClassCollector())->collectIndex($src)['PTGS\\TypeBridge\\Tests\\Fixture\\NativeTypedFixtures\\Notes\\Response\\ShowNoteResponse'];
        $notes = self::emit($src)['Notes'];

        self::assertSame('array<array-key, mixed>', self::rawTypes($response)['meta']);
        self::assertSame('string|null', self::rawTypes($response)['note']);
        self::assertSame('int|null', self::rawTypes($response)['rank']);
        // A class is the one Reflection names, wherever the property is written; `self` is the class.
        self::assertSame('\\' . NoteSummary::class . '|null', self::rawTypes($response)['summary']);
        self::assertStringContainsString("export interface ShowNoteResponse {\n  id: string;\n  rank: number | null;\n  summary: NoteSummary | null;\n  previous: ShowNoteResponse | null;\n  meta: Record<string, unknown> | unknown[];\n  note: string | null;\n", $notes);
        self::assertMatchesRegularExpression('~^  count: (number \\| Record<string, unknown> \\| unknown\\[\\]|Record<string, unknown> \\| unknown\\[\\] \\| number);$~m', $notes);
    }

    public function test_a_native_parent_type_is_the_parent_class(): void
    {
        self::assertStringContainsString("export interface ShowNoteVersionResponse {\n  previous: NoteVersion | null;\n  number: number;\n}", self::emit(self::fixtures('NativeTypedFixtures'))['Notes']);
    }

    public function test_a_library_base_classs_property_with_only_a_native_type_is_published_as_that_type_says(): void
    {
        // No docblock can be added to a library's class: what its native type says is what is sent.
        $src = self::fixtures('NativeTypedFixtures');

        self::assertStringContainsString("export interface NoteFailedResponse {\n  details: Record<string, unknown> | unknown[];\n  hint: string | null;\n}", self::emit($src)['Notes']);
    }

    public function test_a_library_base_classs_property_naming_a_class_of_the_tree_is_that_classs_type(): void
    {
        // `OwnerView` as the library's file reads it, through its own `use`.
        $src = self::fixtures('LibraryBaseOwnedFixtures');
        $response = (new ResponseClassCollector())->collectIndex($src)[ShowOwnedNoteResponse::class];

        self::assertSame(['id' => 'string', 'owner' => 'OwnerView'], self::rawTypes($response));
        self::assertSame(AbstractOwnedResponse::class, $response->properties[1]->writtenIn);
        self::assertStringContainsString('  owner: OwnerView;', self::emit($src)['Notes']);
    }

    public function test_a_library_base_classs_property_names_what_its_own_file_means(): void
    {
        // `list<Item>` is the library's Item, which nothing in the tree emits: an error naming it,
        // not the Notes module's own `Item` quietly in its place.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(LibraryItem::class);

        self::emit(self::fixtures('LibraryBaseNamedFixtures'));
    }

    public function test_a_library_traits_property_names_what_its_own_file_means(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(LibraryItem::class);

        self::emit(self::fixtures('LibraryTraitNamedFixtures'));
    }

    public function test_a_property_typed_with_an_alias_a_library_declares_says_it_is_never_emitted(): void
    {
        // The library's `Row`, not the module's: and nothing emits the library's.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/"Row" is declared by .*AbstractRowsResponse, outside the source tree.*ListRowsResponse/s');

        (new ResponseClassCollector())->collectIndex(self::fixtures('LibraryBaseAliasFixtures'));
    }

    public function test_a_name_a_library_file_cannot_see_is_an_error_not_a_module_alias(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/`Widget`.*AbstractWidgetsResponse/s');

        (new ResponseClassCollector())->collectIndex(self::fixtures('LibraryBaseUnknownNameFixtures'));
    }

    public function test_a_config_alias_on_an_inherited_property_is_the_emitting_modules_own(): void
    {
        // The base's module emits nothing: a config alias is declared by each module that uses it,
        // never imported from another.
        $notes = self::emit(self::fixtures('InheritedConfigAliasFixtures'), typeAliases: ['UuidStr' => 'string'])['Notes'];

        self::assertStringContainsString('export type UuidStr = string;', $notes);
        self::assertStringContainsString('  id: UuidStr;', $notes);
        self::assertStringContainsString('  owner: UuidStr;', $notes);
        self::assertStringNotContainsString('import', $notes);
    }

    public function test_a_config_alias_on_an_inherited_property_is_not_imported_from_a_module_that_never_declares_it(): void
    {
        $output = self::emit(self::fixtures('InheritedConfigAliasModuleFixtures'), typeAliases: ['UuidStr' => 'string']);

        self::assertStringContainsString('export type UuidStr = string;', $output['Notes']);
        self::assertStringContainsString('  id: UuidStr;', $output['Notes']);
        self::assertStringNotContainsString('import', $output['Notes']);
        self::assertStringNotContainsString('UuidStr', $output['Common']);
    }

    public function test_a_published_property_typed_with_an_imported_php_stan_only_alias_says_so(): void
    {
        // Another class's `Row` is emitted under the same name: the import is not quietly that one.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/"Row" is #\\[PhpStanOnly\\] on .*RowSource.*ShowRowResponse/s');

        (new ResponseClassCollector())->collectIndex(self::fixtures('PhpStanOnlyImportedFixtures'));
    }

    public function test_a_published_property_typed_with_a_php_stan_only_alias_says_so(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Row.*#\\[PhpStanOnly\\]|#\\[PhpStanOnly\\].*Row/s');

        self::emit(self::fixtures('PhpStanOnlyUsedFixtures'));
    }

    public function test_a_class_whose_type_is_misconfigured_fails_rather_than_becoming_a_module_alias(): void
    {
        // Common\Model\Money's `_self` is imported from a PHPStan-only class, so it has no type: that
        // is an error to name, not a cue to use the Notes module's own `Money` instead.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('declares no such type that is emitted');

        self::emit(self::fixtures('ClassSelfUnemittedFixtures'));
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
     * @param array<string, string> $typeAliases
     *
     * @return array<string, string>
     */
    private static function emit(string $src = self::SRC, ?TypeScriptNaming $naming = null, array $typeAliases = []): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            naming: $naming ?? new TypeScriptNaming(),
            typeAliases: $typeAliases,
        ))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responseCollector->collect($src),
            (new EndpointContractCollector())->collect($src, $responseCollector->collectIndex($src)),
        );
    }
}
