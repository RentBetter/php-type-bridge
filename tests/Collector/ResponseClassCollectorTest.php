<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Tests\Fixture\FixtureProject;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Response\ValidationErrorResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\CreateProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\DeleteProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ShowProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\UpdateProjectResponse;

final class ResponseClassCollectorTest extends TestCase
{
    public function test_it_collects_response_classes_and_statuses(): void
    {
        $responses = (new ResponseClassCollector())->collectIndex(FixtureProject::srcDir());

        self::assertArrayHasKey(ShowProjectResponse::class, $responses);
        self::assertArrayHasKey(CreateProjectResponse::class, $responses);
        self::assertArrayHasKey(UpdateProjectResponse::class, $responses);
        self::assertArrayHasKey(DeleteProjectResponse::class, $responses);
        self::assertArrayHasKey(ValidationErrorResponse::class, $responses);

        $show = $responses[ShowProjectResponse::class];
        self::assertSame(200, $show->status);
        self::assertFalse($show->error);
        self::assertCount(1, $show->properties);
        self::assertInstanceOf(NameRefType::class, $show->properties[0]->parsed);
        self::assertSame('ProjectView', $show->properties[0]->parsed->name);

        $create = $responses[CreateProjectResponse::class];
        self::assertSame(201, $create->status);

        $update = $responses[UpdateProjectResponse::class];
        self::assertSame(200, $update->status);

        $delete = $responses[DeleteProjectResponse::class];
        self::assertSame(204, $delete->status);
        self::assertCount(0, $delete->properties);

        $validation = $responses[ValidationErrorResponse::class];
        self::assertTrue($validation->error);
        self::assertSame(422, $validation->status);
    }
}
