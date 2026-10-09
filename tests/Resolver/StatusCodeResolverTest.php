<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Resolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Contract\ApiErrorResponse;
use PTGS\TypeBridge\Contract\ApiSuccessResponse;
use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\Status\HttpAccepted;
use PTGS\TypeBridge\Status\HttpBadRequest;
use PTGS\TypeBridge\Status\HttpConflict;
use PTGS\TypeBridge\Status\HttpCreated;
use PTGS\TypeBridge\Status\HttpForbidden;
use PTGS\TypeBridge\Status\HttpGone;
use PTGS\TypeBridge\Status\HttpInternalServerError;
use PTGS\TypeBridge\Status\HttpNoContent;
use PTGS\TypeBridge\Status\HttpNotFound;
use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Status\HttpUnauthorized;
use PTGS\TypeBridge\Status\HttpUnprocessableEntity;
use ReflectionClass;
use RuntimeException;

final class StatusCodeResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, int, bool}>
     */
    public static function statusInterfaces(): iterable
    {
        yield 'ok' => [HttpOk::class, 200, false];
        yield 'created' => [HttpCreated::class, 201, false];
        yield 'accepted' => [HttpAccepted::class, 202, false];
        yield 'no content' => [HttpNoContent::class, 204, false];
        yield 'bad request' => [HttpBadRequest::class, 400, true];
        yield 'unauthorized' => [HttpUnauthorized::class, 401, true];
        yield 'forbidden' => [HttpForbidden::class, 403, true];
        yield 'not found' => [HttpNotFound::class, 404, true];
        yield 'conflict' => [HttpConflict::class, 409, true];
        yield 'gone' => [HttpGone::class, 410, true];
        yield 'unprocessable entity' => [HttpUnprocessableEntity::class, 422, true];
        yield 'internal server error' => [HttpInternalServerError::class, 500, true];
    }

    /**
     * @param class-string $interface
     */
    #[DataProvider('statusInterfaces')]
    public function test_a_response_class_answers_the_status_its_marker_names(string $interface, int $status, bool $error): void
    {
        self::assertTrue(interface_exists($interface));
        self::assertSame($error, is_subclass_of($interface, ApiErrorResponse::class));
        self::assertSame(!$error, is_subclass_of($interface, ApiSuccessResponse::class));

        $class = new ReflectionClass($interface);

        self::assertSame($status, StatusCodeResolver::statusCodeOf($interface));
        self::assertSame($status, (new StatusCodeResolver())->resolve($class));
        self::assertSame($error, (new StatusCodeResolver())->isError($class));
    }

    public function test_a_thrown_gone_response_answers_410(): void
    {
        $gone = new class('That link has expired.') extends ThrowableApiResponse implements HttpGone {};

        self::assertSame(410, $gone->getStatusCode());
        self::assertTrue((new StatusCodeResolver())->isError(new ReflectionClass($gone)));
    }

    public function test_a_class_with_two_markers_is_refused(): void
    {
        $both = new class('Gone, or never there.') extends ThrowableApiResponse implements HttpNotFound, HttpGone {};

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement exactly one known HTTP status interface, found 2');

        StatusCodeResolver::statusCodeOf($both::class);
    }
}
