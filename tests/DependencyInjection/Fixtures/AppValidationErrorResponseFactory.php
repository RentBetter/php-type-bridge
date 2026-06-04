<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\DependencyInjection\Fixtures;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Form\ValidationErrorResponseFactory;
use PTGS\TypeBridge\Response\ValidationErrorResponse;

/**
 * Stand-in for an application's own factory, used to prove the bundle's default binding
 * can be overridden by aliasing ValidationErrorResponseFactory in the app's config.
 */
final readonly class AppValidationErrorResponseFactory implements ValidationErrorResponseFactory
{
    public function create(array $errors): ThrowableApiResponse
    {
        return new ValidationErrorResponse($errors, 'App-specific message');
    }
}
