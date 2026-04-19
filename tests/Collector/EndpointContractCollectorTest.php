<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\CreateProjectRequestType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\ProjectFiltersType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\UpdateProjectRequestType;
use PTGS\TypeBridge\Tests\Fixture\FixtureProject;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class EndpointContractCollectorTest extends TestCase
{
    public function test_it_collects_flat_endpoint_contracts(): void
    {
        $srcDir = FixtureProject::srcDir();
        $responses = (new ResponseClassCollector())->collectIndex($srcDir);
        $contracts = (new EndpointContractCollector())->collect($srcDir, $responses);

        self::assertArrayHasKey('Projects', $contracts);
        self::assertCount(6, $contracts['Projects']);

        $archive = current(array_filter(
            $contracts['Projects'],
            static fn($contract): bool => '__invoke' === $contract->methodName,
        ));

        self::assertNotFalse($archive);
        self::assertSame('ArchiveProject', $archive->name);

        $index = current(array_filter(
            $contracts['Projects'],
            static fn($contract): bool => 'index' === $contract->methodName,
        ));

        self::assertNotFalse($index);
        self::assertNotNull($index->request);
        self::assertNotNull($index->request->query);
        self::assertSame(ProjectFiltersType::class, $index->request->query->formClass);
        self::assertSame('ProjectFiltersData', $index->request->query->typeName);
        self::assertCount(3, $index->request->query->fields);
        self::assertSame('search', $index->request->query->fields[0]->name);
        self::assertSame(TextType::class, $index->request->query->fields[0]->formTypeClass);
        self::assertFalse($index->request->query->fields[0]->required);

        $show = current(array_filter(
            $contracts['Projects'],
            static fn($contract): bool => 'show' === $contract->methodName,
        ));

        self::assertNotFalse($show);
        self::assertSame('ProjectShow', $show->name);
        self::assertNotNull($show->request);
        self::assertNotNull($show->request->path);
        self::assertSame('ProjectPathParams', $show->request->path->typeName);
        self::assertSame([200, 422], array_map(
            static fn($response): int => $response->status,
            $show->responses,
        ));

        $create = current(array_filter(
            $contracts['Projects'],
            static fn($contract): bool => 'create' === $contract->methodName,
        ));

        self::assertNotFalse($create);
        self::assertNotNull($create->request);
        self::assertNotNull($create->request->body);
        self::assertSame(CreateProjectRequestType::class, $create->request->body->formClass);
        self::assertSame('CreateProjectRequestData', $create->request->body->typeName);
        self::assertCount(1, $create->request->body->fields);
        self::assertSame('project', $create->request->body->fields[0]->name);
        self::assertTrue($create->request->body->fields[0]->compound);
        self::assertCount(5, $create->request->body->fields[0]->children);
        self::assertSame('status', $create->request->body->fields[0]->children[2]->name);
        self::assertSame(EnumType::class, $create->request->body->fields[0]->children[2]->formTypeClass);
        self::assertSame('settings', $create->request->body->fields[0]->children[3]->name);
        self::assertTrue($create->request->body->fields[0]->children[3]->compound);
        self::assertCount(2, $create->request->body->fields[0]->children[3]->children);

        $update = current(array_filter(
            $contracts['Projects'],
            static fn($contract): bool => 'update' === $contract->methodName,
        ));

        self::assertNotFalse($update);
        self::assertNotNull($update->request);
        self::assertNotNull($update->request->body);
        self::assertNotNull($update->request->path);
        self::assertSame(UpdateProjectRequestType::class, $update->request->body->formClass);
        self::assertSame('UpdateProjectRequestData', $update->request->body->typeName);
        self::assertSame('ProjectPathParams', $update->request->path->typeName);
        self::assertCount(1, $update->request->body->fields);
        self::assertSame('project', $update->request->body->fields[0]->name);
        self::assertCount(5, $update->request->body->fields[0]->children);
        self::assertSame('changeSummary', $update->request->body->fields[0]->children[4]->name);
        self::assertSame(TextType::class, $update->request->body->fields[0]->children[4]->formTypeClass);
    }
}
