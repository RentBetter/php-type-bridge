// AUTO-GENERATED. DO NOT EDIT.

import type { ProjectPathParams, TimestampedView, ValidationErrorResponse } from '../Common/genTypes';

// Enums
export type ProjectStatus = 'draft' | 'active';

// Shapes
export interface ProjectStatusData {
  value: ProjectStatus;
  label: string;
  color: string;
}

export interface CreateProjectInputData extends ProjectMutationData {
  nickname?: string;
}

export interface CreateProjectRequestData {
  project: CreateProjectInputData;
}

export interface ProjectFiltersData {
  search?: string;
  page?: number;
  archived?: boolean;
}

export interface ProjectMutationData {
  name: string;
  clientId: string;
  status: ProjectStatus;
  settings: ProjectSettingsData;
}

export interface ProjectSettingsData {
  notifyOwner: boolean;
  timezone: string;
}

export interface UpdateProjectInputData extends ProjectMutationData {
  changeSummary?: string;
}

export interface UpdateProjectRequestData {
  project: UpdateProjectInputData;
}

export interface ClientView {
  id: string;
  name: string;
}

export interface ProjectAdminView extends ProjectBaseView {
  internalNotes: string | null;
  auditTrail: string[];
  statusDetail: ProjectStatusData;
}

export interface ProjectBaseView {
  id: string;
  name: string;
  status: ProjectStatus;
}

export interface ProjectOwnerView extends ProjectBaseView {
  canEdit: boolean;
  ownerNotes?: string;
}

export interface ProjectView extends TimestampedView {
  name: string;
  client: ClientView;
  status: ProjectStatus;
  tags: string[];
  nickname?: string;
}

// Responses
export interface CreateProjectResponse {
  project: ProjectView;
}

export type DeleteProjectResponse = null;

export interface ShowProjectResponse {
  project: ProjectView;
}

export interface UpdateProjectResponse {
  project: ProjectView;
}

// Endpoint inputs
export type ArchiveProjectPathParams = ProjectPathParams;

export type ListProjectsQuery = ProjectFiltersData;

export type ShowProjectPathParams = ProjectPathParams;

export type CreateProjectBody = CreateProjectRequestData;

export type UpdateProjectBody = UpdateProjectRequestData;
export type UpdateProjectPathParams = ProjectPathParams;

export type DeleteProjectPathParams = { id: number };

// Endpoint results
export type EndpointResult<M extends Record<number, unknown>> = {
  [S in keyof M & number]: {
    ok: S extends 200 | 201 | 202 | 204 ? true : false;
    status: S;
    data: M[S];
  };
}[keyof M & number];

export type ArchiveProjectEndpointMap = {
  200: UpdateProjectResponse;
  422: ValidationErrorResponse;
};
export type ArchiveProjectResult = EndpointResult<ArchiveProjectEndpointMap>;

export type ListProjectsEndpointMap = {
  200: ShowProjectResponse;
  422: ValidationErrorResponse;
};
export type ListProjectsResult = EndpointResult<ListProjectsEndpointMap>;

export type ShowProjectEndpointMap = {
  200: ShowProjectResponse;
  422: ValidationErrorResponse;
};
export type ShowProjectResult = EndpointResult<ShowProjectEndpointMap>;

export type CreateProjectEndpointMap = {
  201: CreateProjectResponse;
  422: ValidationErrorResponse;
};
export type CreateProjectResult = EndpointResult<CreateProjectEndpointMap>;

export type UpdateProjectEndpointMap = {
  200: UpdateProjectResponse;
  422: ValidationErrorResponse;
};
export type UpdateProjectResult = EndpointResult<UpdateProjectEndpointMap>;

export type DeleteProjectEndpointMap = {
  204: DeleteProjectResponse;
};
export type DeleteProjectResult = EndpointResult<DeleteProjectEndpointMap>;
