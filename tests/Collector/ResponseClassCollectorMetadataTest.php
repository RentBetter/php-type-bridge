<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Http\ResponseFields;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\Response\ListNotesResponse;
use PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\Response\RecentNotesResponse;

/**
 * A response property marked #[Metadata] is published under the key it is sent as: its name
 * behind a `$`. It stays the PHP property it is by name.
 */
final class ResponseClassCollectorMetadataTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/MetadataResponseFixtures';

    public function test_a_metadata_property_is_collected_under_its_name_and_keyed_behind_a_dollar(): void
    {
        $response = (new ResponseClassCollector())->collectIndex(self::SRC)[ListNotesResponse::class];
        $keys = array_combine(
            array_map(static fn(CollectedResponseProperty $property): string => $property->name, $response->properties),
            array_map(static fn(CollectedResponseProperty $property): string => $property->key(), $response->properties),
        );

        self::assertSame(['notes' => 'notes', 'pagination' => '$pagination'], $keys);
    }

    public function test_a_redeclared_metadata_property_keeps_its_key(): void
    {
        $response = (new ResponseClassCollector())->collectIndex(self::SRC)[RecentNotesResponse::class];

        self::assertSame(['notes', '$pagination'], self::keys($response->properties));
    }

    /**
     * What is published and what is sent are read from the same property, by two readers: the
     * collector and {@see ResponseFields::of()}. They agree on every key.
     */
    public function test_each_response_is_published_under_the_keys_it_is_sent_under(): void
    {
        $page = ['offset' => 0, 'limit' => 20, 'hasMore' => false];
        $index = (new ResponseClassCollector())->collectIndex(self::SRC);

        foreach ([new ListNotesResponse(notes: [], pagination: $page), new RecentNotesResponse(notes: [], pagination: $page)] as $response) {
            $published = self::keys($index[$response::class]->properties);
            $sent = array_keys(ResponseFields::of($response));
            sort($published);
            sort($sent);

            self::assertSame($sent, $published, $response::class);
        }
    }

    public function test_it_emits_under_its_key(): void
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        $output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );

        self::assertStringContainsString("export interface ListNotesResponse {\n  notes: NoteView[];\n  \$pagination: PageData;\n}", $output['Notes']);
    }

    /**
     * @param list<CollectedResponseProperty> $properties
     *
     * @return list<string>
     */
    private static function keys(array $properties): array
    {
        return array_map(static fn(CollectedResponseProperty $property): string => $property->key(), $properties);
    }
}
