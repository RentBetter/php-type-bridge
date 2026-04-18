<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
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
        self::assertSame('TimestampedView', $projectView->parsed->base->name);

        $fields = [];
        foreach ($projectView->parsed->extra->fields as $field) {
            $fields[$field->name] = $field;
        }

        self::assertInstanceOf(NameRefType::class, $fields['client']->type);
        self::assertSame('ClientView', $fields['client']->type->name);
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
        self::assertSame('ProjectBaseView', $ownerView->parsed->base->name);

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
        self::assertSame('ProjectBaseView', $adminView->parsed->base->name);

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
}
