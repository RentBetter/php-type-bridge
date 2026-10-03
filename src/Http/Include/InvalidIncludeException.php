<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

use InvalidArgumentException;

/**
 * A request's `?include=` or `?expand=` that the response cannot answer: malformed, or naming
 * something the response does not have. The client's mistake, not the server's —
 * IncludeResponseSubscriber answers it with the app's 422 (its ValidationErrorResponseFactory),
 * the parameter as the error's path.
 */
final class InvalidIncludeException extends InvalidArgumentException
{
    /**
     * @param 'include'|'expand' $parameter
     */
    public function __construct(
        public readonly string $parameter,
        string $message,
    ) {
        parent::__construct($message);
    }
}
