<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Response;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpUnprocessableEntity;

/**
 * Default typed 422 response for request/form validation failures.
 *
 * Serialized by TypeBridgeThrowableListener via get_object_vars(), so the public
 * properties below ARE the JSON body:
 *
 *   {
 *     "message": "Validation failed",
 *     "errors": [{"path": "field.path", "message": "..."}]
 *   }
 *
 * The errors property uses a concrete array{}/list<> shape rather than a generic
 * array<K, V> so the bundle's own PhpDocShapeParser can parse and emit it.
 *
 * Apps that need a richer envelope ship their own ThrowableApiResponse and bind a
 * custom ValidationErrorResponseFactory; this class is the bundle's default.
 */
final class ValidationErrorResponse extends ThrowableApiResponse implements HttpUnprocessableEntity
{
    /**
     * Redeclares \Exception's inherited (protected) $message as public so the
     * TypeBridgeThrowableListener's get_object_vars() call exposes it on the wire. It
     * must stay untyped — PHP forbids adding a type to a property already declared by
     * \Exception.
     *
     * @var string
     */
    public $message;

    /**
     * @param list<array{path: string, message: string}> $errors
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message);
    }
}
