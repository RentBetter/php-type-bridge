<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Http\TypeBridgeThrowableListener;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\Tests\Http\Fixtures\NotFoundError;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class TypeBridgeThrowableListenerTest extends TestCase
{
    public function test_it_renders_error_response_with_status_from_marker_and_body_from_object_vars(): void
    {
        $event = $this->exceptionEvent(new NotFoundError(resource: 'project'));

        (new TypeBridgeThrowableListener(new StatusCodeResolver()))($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());
        // get_object_vars on a ThrowableApiResponse exposes the public $resource plus the
        // public-promoted message; the listener serializes only what is public on the DTO.
        self::assertJsonStringEqualsJsonString('{"resource":"project"}', (string) $response->getContent());
    }

    public function test_it_stops_propagation_once_handled(): void
    {
        $event = $this->exceptionEvent(new NotFoundError(resource: 'project'));

        (new TypeBridgeThrowableListener(new StatusCodeResolver()))($event);

        self::assertTrue($event->isPropagationStopped());
    }

    public function test_it_ignores_non_api_throwables(): void
    {
        $event = $this->exceptionEvent(new RuntimeException('boom'));

        (new TypeBridgeThrowableListener(new StatusCodeResolver()))($event);

        self::assertNull($event->getResponse());
        self::assertFalse($event->isPropagationStopped());
    }

    private function exceptionEvent(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(
            new StubHttpKernel(),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}
