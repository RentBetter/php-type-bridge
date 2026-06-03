// AUTO-GENERATED. DO NOT EDIT.

// Shapes
export interface ProjectPathParams {
  id: string;
}

export interface TimestampedView {
  id: string;
  createdAt: string;
  updatedAt: string;
}

// Responses
export interface ValidationErrorResponse {
  errors: { path: string; message: string }[];
}
