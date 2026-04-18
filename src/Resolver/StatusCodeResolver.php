<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Resolver;

use PTGS\TypeBridge\Contract\ApiErrorResponse;
use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpAccepted;
use PTGS\TypeBridge\Status\HttpBadRequest;
use PTGS\TypeBridge\Status\HttpConflict;
use PTGS\TypeBridge\Status\HttpCreated;
use PTGS\TypeBridge\Status\HttpForbidden;
use PTGS\TypeBridge\Status\HttpInternalServerError;
use PTGS\TypeBridge\Status\HttpNoContent;
use PTGS\TypeBridge\Status\HttpNotFound;
use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Status\HttpUnauthorized;
use PTGS\TypeBridge\Status\HttpUnprocessableEntity;
use ReflectionClass;
use RuntimeException;

final class StatusCodeResolver
{
    /** @var array<class-string, int> */
    private const STATUS_INTERFACES = [
        HttpOk::class => 200,
        HttpCreated::class => 201,
        HttpAccepted::class => 202,
        HttpNoContent::class => 204,
        HttpBadRequest::class => 400,
        HttpUnauthorized::class => 401,
        HttpForbidden::class => 403,
        HttpNotFound::class => 404,
        HttpConflict::class => 409,
        HttpUnprocessableEntity::class => 422,
        HttpInternalServerError::class => 500,
    ];

    public function resolve(ReflectionClass $class): int
    {
        $matches = $this->matchingInterfaces($class);
        if (1 !== \count($matches)) {
            throw new RuntimeException(\sprintf(
                'Response class "%s" must implement exactly one known HTTP status interface, found %d.',
                $class->getName(),
                \count($matches),
            ));
        }

        return self::STATUS_INTERFACES[$matches[0]];
    }

    public function isError(ReflectionClass $class): bool
    {
        return $class->implementsInterface(ApiErrorResponse::class);
    }

    public function assertResponseClass(ReflectionClass $class): void
    {
        if (!$class->implementsInterface(ApiResponse::class)) {
            throw new RuntimeException(\sprintf('Class "%s" does not implement %s.', $class->getName(), ApiResponse::class));
        }

        $this->resolve($class);
    }

    /**
     * @return list<class-string>
     */
    private function matchingInterfaces(ReflectionClass $class): array
    {
        $matches = [];

        foreach (self::STATUS_INTERFACES as $interface => $status) {
            if ($class->implementsInterface($interface)) {
                $matches[] = $interface;
            }
        }

        return $matches;
    }
}
