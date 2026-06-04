<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Http\TypeBridgeResponseSubscriber;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\Tests\Http\Fixtures\NoContentResponse;
use PTGS\TypeBridge\Tests\Http\Fixtures\OkResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class TypeBridgeResponseSubscriberTest extends TestCase
{
    public function test_it_renders_success_response_with_status_from_marker_and_body_from_object_vars(): void
    {
        $event = $this->viewEvent(new OkResponse(id: 'abc', count: 3));

        (new TypeBridgeResponseSubscriber(new StatusCodeResolver()))($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"id":"abc","count":3}', $response->getContent());
    }

    public function test_it_renders_no_content_marker_as_empty_204(): void
    {
        $event = $this->viewEvent(new NoContentResponse());

        (new TypeBridgeResponseSubscriber(new StatusCodeResolver()))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function test_it_ignores_non_api_responses(): void
    {
        $event = $this->viewEvent(['plain' => 'array']);

        (new TypeBridgeResponseSubscriber(new StatusCodeResolver()))($event);

        self::assertNull($event->getResponse());
    }

    private function viewEvent(mixed $controllerResult): ViewEvent
    {
        return new ViewEvent(
            new StubHttpKernel(),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult,
        );
    }
}
