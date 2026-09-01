<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Contract;

use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Base for error responses that are thrown rather than returned.
 *
 * It is an HttpExceptionInterface as well as an ApiErrorResponse. The status comes from the
 * class's HTTP marker interface, resolved the same way TypeBridgeThrowableListener resolves
 * it, and that is what tells Symfony's ErrorListener — and everything keyed on it: log
 * level, Sentry filters, fingers-crossed exclusions — that a thrown 4xx response is a client
 * error, not an uncaught exception to report as critical.
 */
abstract class ThrowableApiResponse extends \RuntimeException implements ApiErrorResponse, HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return StatusCodeResolver::statusCodeOf(static::class);
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
