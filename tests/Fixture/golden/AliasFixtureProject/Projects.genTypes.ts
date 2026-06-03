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
