// AUTO-GENERATED. DO NOT EDIT.

import type { SharedResponse as CommonSharedResponse } from '../Common/genTypes';

// Responses
export interface SharedResponse {
  reason: string;
}

// Endpoint results
export type EndpointResult<M extends Record<number, unknown>> = {
  [S in keyof M & number]: {
    ok: S extends 200 | 201 | 202 | 204 ? true : false;
    status: S;
    data: M[S];
  };
}[keyof M & number];

export type SharedEndpointMap = {
  200: CommonSharedResponse;
  404: SharedResponse;
};
export type SharedResult = EndpointResult<SharedEndpointMap>;

// Endpoints
/** How to call an endpoint. Its response map and inputs ride along in the type, for helpers that call it. */
export interface Endpoint<M extends Record<number, unknown> = Record<number, unknown>, I = Record<never, never>> {
  readonly method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  /** The route's path, `{placeholders}` and all. */
  readonly path: string;
  /** Never set at runtime: the types a helper reads off the endpoint. */
  readonly types?: { responses: M; input: I };
}

export const Shared: Endpoint<SharedEndpointMap> = {
  method: 'GET',
  path: '/api/shared',
};
