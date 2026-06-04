<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Form\Fixtures;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Form\ValidationErrorResponseFactory;

/**
 * Test factory that re-shapes the bundle's lean {path, message} errors into the app's
 * own CustomValidationError envelope.
 */
final readonly class CustomValidationErrorResponseFactory implements ValidationErrorResponseFactory
{
    public function create(array $errors): ThrowableApiResponse
    {
        return new CustomValidationError($errors);
    }
}
