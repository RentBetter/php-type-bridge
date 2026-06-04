<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Form;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Response\ValidationErrorResponse;

/**
 * Default factory: produces the bundle's own ValidationErrorResponse (the lean
 * {message, errors:[{path, message}]} envelope). Bound by the bundle's extension;
 * apps replace the binding to emit their own response shape.
 */
final readonly class DefaultValidationErrorResponseFactory implements ValidationErrorResponseFactory
{
    public function create(array $errors): ThrowableApiResponse
    {
        return new ValidationErrorResponse($errors);
    }
}
