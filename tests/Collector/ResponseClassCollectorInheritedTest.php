<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
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

    public function test_the_base_classs_import_travels_with_the_property(): void
    {
        $imports = (new ResponseClassCollector())->collectIndex(self::SRC)[NoteGoneResponse::class]->imports;

        self::assertSame(['ErrorBodyData'], array_map(static fn(object $import): string => $import->localAlias, $imports));
        self::assertSame('Common', $imports[0]->targetDomain);
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
        self::assertStringContainsString('export type Envelope = {', $output['Common']);
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
    private static function emit(string $src = self::SRC): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responseCollector->collect($src),
            (new EndpointContractCollector())->collect($src, $responseCollector->collectIndex($src)),
        );
    }
}
