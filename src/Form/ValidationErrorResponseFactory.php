<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Form;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;

/**
 * Builds the throwable thrown by RequestFormProcessor when JSON-body form validation
 * fails. Consuming apps use different 422 envelopes (leaner {message, errors:[{path,
 * message}]} vs richer {message, code, errors:[{message, path, code}], extra}), so the
 * concrete response is supplied by an implementation of this interface.
 *
 * The bundle binds DefaultValidationErrorResponseFactory by default; apps override the
 * binding in their own service config to inject their own factory.
 */
interface ValidationErrorResponseFactory
{
    /**
     * @param list<array{path: string, message: string}> $errors
     */
    public function create(array $errors): ThrowableApiResponse;
}
