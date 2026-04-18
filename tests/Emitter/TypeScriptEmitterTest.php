<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\FixtureProject;
use RuntimeException;

final class TypeScriptEmitterTest extends TestCase
{
    public function test_it_emits_types_responses_and_endpoint_result_unions(): void
    {
        $srcDir = FixtureProject::srcDir();
        $typeDomains = (new PhpDocTypeCollector())->collect($srcDir);
        $responseCollector = new ResponseClassCollector();
        $responseDomains = $responseCollector->collect($srcDir);
        $contracts = (new EndpointContractCollector())->collect($srcDir, $responseCollector->collectIndex($srcDir));

        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($srcDir);

        $output = (new TypeScriptEmitter($enumResolver, new DomainMapper('/tmp/type-bridge-output')))
            ->emit($typeDomains, $responseDomains, $contracts);

        self::assertArrayHasKey('Projects', $output);
        self::assertArrayHasKey('Common', $output);

        $projects = $output['Projects'];
        self::assertStringContainsString("import type { ProjectPathParams, TimestampedView } from '../Common/genTypes';", $projects);
        self::assertStringContainsString("export type ProjectStatus = 'draft' | 'active';", $projects);
        self::assertStringContainsString('export interface ProjectStatusData {', $projects);
        self::assertStringContainsString('value: ProjectStatus;', $projects);
        self::assertStringContainsString('label: string;', $projects);
        self::assertStringContainsString('color: string;', $projects);
        self::assertStringContainsString('export interface ProjectBaseView {', $projects);
        self::assertStringContainsString('export interface ProjectView extends TimestampedView {', $projects);
        self::assertStringContainsString('client: ClientView;', $projects);
        self::assertStringContainsString('status: ProjectStatus;', $projects);
        self::assertStringContainsString('export interface ProjectOwnerView extends ProjectBaseView {', $projects);
        self::assertStringContainsString('canEdit: boolean;', $projects);
        self::assertStringContainsString('ownerNotes: string | null;', $projects);
        self::assertStringContainsString('export interface ProjectAdminView extends ProjectBaseView {', $projects);
        self::assertStringContainsString('internalNotes: string | null;', $projects);
        self::assertStringContainsString('auditTrail: string[];', $projects);
        self::assertStringContainsString('statusDetail: ProjectStatusData;', $projects);
        self::assertStringContainsString('export interface ProjectFiltersData {', $projects);
        self::assertStringContainsString('search?: string;', $projects);
        self::assertStringContainsString('page?: number;', $projects);
        self::assertStringContainsString('archived?: boolean;', $projects);
        self::assertStringContainsString('export interface ProjectMutationData {', $projects);
        self::assertStringContainsString('status: ProjectStatus;', $projects);
        self::assertStringContainsString('settings: ProjectSettingsData;', $projects);
        self::assertStringContainsString('export interface CreateProjectInputData extends ProjectMutationData {', $projects);
        self::assertStringContainsString('nickname?: string;', $projects);
        self::assertStringContainsString('export interface CreateProjectRequestData {', $projects);
        self::assertStringContainsString('project: CreateProjectInputData;', $projects);
        self::assertStringContainsString('export interface UpdateProjectInputData extends ProjectMutationData {', $projects);
        self::assertStringContainsString('changeSummary?: string;', $projects);
        self::assertStringContainsString('export interface UpdateProjectRequestData {', $projects);
        self::assertStringContainsString('project: UpdateProjectInputData;', $projects);
        self::assertStringContainsString('export interface ShowProjectResponse {', $projects);
        self::assertStringContainsString('project: ProjectView;', $projects);
        self::assertStringContainsString('export interface UpdateProjectResponse {', $projects);
        self::assertStringContainsString('project: ProjectView;', $projects);
        self::assertStringContainsString('export type DeleteProjectResponse = null;', $projects);
        self::assertStringContainsString('export type ProjectIndexQuery = ProjectFiltersData;', $projects);
        self::assertStringContainsString('export type ProjectShowPathParams = ProjectPathParams;', $projects);
        self::assertStringContainsString('export type ProjectCreateBody = CreateProjectRequestData;', $projects);
        self::assertStringContainsString('export type ProjectUpdateBody = UpdateProjectRequestData;', $projects);
        self::assertStringContainsString('export type ProjectUpdatePathParams = ProjectPathParams;', $projects);
        self::assertStringContainsString('export type ProjectShowEndpointMap = {', $projects);
        self::assertStringContainsString('  200: ShowProjectResponse;', $projects);
        self::assertStringContainsString('  422: ValidationErrorResponse;', $projects);
        self::assertStringContainsString('export type ProjectShowResult = EndpointResult<ProjectShowEndpointMap>;', $projects);
        self::assertStringContainsString('export type ProjectUpdateEndpointMap = {', $projects);
        self::assertStringContainsString('  200: UpdateProjectResponse;', $projects);
        self::assertStringContainsString('  422: ValidationErrorResponse;', $projects);
        self::assertStringContainsString('export type ProjectUpdateResult = EndpointResult<ProjectUpdateEndpointMap>;', $projects);

        $common = $output['Common'];
        self::assertStringContainsString('export interface TimestampedView {', $common);
        self::assertStringContainsString('export interface ProjectPathParams {', $common);
        self::assertStringContainsString('id: string;', $common);
    }

    public function test_it_supports_custom_typescript_naming(): void
    {
        $srcDir = FixtureProject::srcDir();
        $typeDomains = (new PhpDocTypeCollector())->collect($srcDir);
        $responseCollector = new ResponseClassCollector();
        $responseDomains = $responseCollector->collect($srcDir);
        $contracts = (new EndpointContractCollector())->collect($srcDir, $responseCollector->collectIndex($srcDir));

        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($srcDir);

        $output = (new TypeScriptEmitter(
            $enumResolver,
            new DomainMapper('/tmp/type-bridge-output'),
            new TypeScriptNaming(
                interfacePrefix: 'I',
                enumValueSuffix: 'Id',
                enumShapeSuffix: '',
                queryAliasSuffix: 'QueryParams',
                bodyAliasSuffix: 'Payload',
                pathAliasSuffix: 'RouteParams',
                endpointMapSuffix: 'Responses',
                endpointResultSuffix: 'Outcome',
            ),
        ))->emit($typeDomains, $responseDomains, $contracts);

        $projects = $output['Projects'];
        self::assertStringContainsString("import type { IProjectPathParams, ITimestampedView } from '../Common/genTypes';", $projects);
        self::assertStringContainsString("export type ProjectStatusId = 'draft' | 'active';", $projects);
        self::assertStringContainsString('export interface IProjectStatus {', $projects);
        self::assertStringContainsString('value: ProjectStatusId;', $projects);
        self::assertStringContainsString('statusDetail: IProjectStatus;', $projects);
        self::assertStringContainsString('export interface IProjectBaseView {', $projects);
        self::assertStringContainsString('export interface IProjectView extends ITimestampedView {', $projects);
        self::assertStringContainsString('status: ProjectStatusId;', $projects);
        self::assertStringContainsString('export interface ICreateProjectRequestData {', $projects);
        self::assertStringContainsString('export interface IUpdateProjectRequestData {', $projects);
        self::assertStringContainsString('export interface IShowProjectResponse {', $projects);
        self::assertStringContainsString('export type ProjectIndexQueryParams = IProjectFiltersData;', $projects);
        self::assertStringContainsString('export type ProjectShowRouteParams = IProjectPathParams;', $projects);
        self::assertStringContainsString('export type ProjectCreatePayload = ICreateProjectRequestData;', $projects);
        self::assertStringContainsString('export type ProjectUpdatePayload = IUpdateProjectRequestData;', $projects);
        self::assertStringContainsString('export type ProjectShowResponses = {', $projects);
        self::assertStringContainsString('  200: IShowProjectResponse;', $projects);
        self::assertStringContainsString('export type ProjectShowOutcome = EndpointResult<ProjectShowResponses>;', $projects);

        $common = $output['Common'];
        self::assertStringContainsString('export interface ITimestampedView {', $common);
        self::assertStringContainsString('export interface IProjectPathParams {', $common);
    }

    public function test_it_fails_when_custom_names_collide(): void
    {
        $srcDir = FixtureProject::srcDir();
        $typeDomains = (new PhpDocTypeCollector())->collect($srcDir);
        $responseCollector = new ResponseClassCollector();
        $responseDomains = $responseCollector->collect($srcDir);

        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($srcDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision in domain "Projects": "ProjectStatus"');

        (new TypeScriptEmitter(
            $enumResolver,
            new DomainMapper('/tmp/type-bridge-output'),
            new TypeScriptNaming(enumShapeSuffix: ''),
        ))->emit($typeDomains, $responseDomains);
    }
}
