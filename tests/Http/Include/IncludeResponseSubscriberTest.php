<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Form\DefaultValidationErrorResponseFactory;
use PTGS\TypeBridge\Http\Include\EnumCase;
use PTGS\TypeBridge\Http\Include\IncludeResolver;
use PTGS\TypeBridge\Http\Include\IncludeResponseSubscriber;
use PTGS\TypeBridge\Http\Include\Optional;
use PTGS\TypeBridge\Http\Include\Ref;
use PTGS\TypeBridge\Http\Include\RefRegistry;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\Response\ValidationErrorResponse;
use PTGS\TypeBridge\Tests\Http\Fixtures\NoContentResponse;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\RowsResponse;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Thing;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\ThingNormalizer;
use PTGS\TypeBridge\Tests\Http\StubHttpKernel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class IncludeResponseSubscriberTest extends TestCase
{
    public function testItWritesTheBodyTheQueryAskedFor(): void
    {
        $event = $this->viewEvent(self::response(), '/checks?include=rows.debug&expand=rows.thing(name)');

        $this->subscriber()($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            '{"rows":[{"id":"a","thing":{"id":"thing-1","name":"Thing thing-1"},"status":{"id":"OK","name":"Ok","theme":"green"},"debug":true}],"summary":null}',
            $response->getContent(),
        );
    }

    public function testWithoutAQueryMarkersGoOutAsIdsAndOptionalsStayOff(): void
    {
        $event = $this->viewEvent(self::response(), '/checks');

        $this->subscriber()($event);

        self::assertSame('{"rows":[{"id":"a","thing":"thing-1","status":{"id":"OK","name":"Ok","theme":"green"}}],"summary":null}', $event->getResponse()?->getContent());
    }

    public function testTheEnumFormatHeaderAsksForBareIds(): void
    {
        $event = $this->viewEvent(self::response(), '/checks', ['HTTP_X_ENUM_FORMAT' => 'id']);
        $this->subscriber()($event);
        self::assertStringContainsString('"status":"OK"', (string) $event->getResponse()?->getContent());

        $event = $this->viewEvent(self::response(), '/checks', ['HTTP_X_ENUMS' => 'id']);
        $this->subscriber(enumFormatHeader: 'X-Enums')($event);
        self::assertStringContainsString('"status":"OK"', (string) $event->getResponse()?->getContent(), 'The header name is the app\'s');
    }

    public function testAPathTheResponseCannotAnswerIsTheAppsValidationError(): void
    {
        $event = $this->viewEvent(self::response(), '/checks?expand=rows.status.name');

        try {
            $this->subscriber()($event);
            self::fail('The expand was not refused');
        } catch (ValidationErrorResponse $error) {
            self::assertSame(422, $error->getStatusCode());
            self::assertSame('expand', $error->errors[0]['path']);
            self::assertStringContainsString('is an enum', $error->errors[0]['message']);
        }
    }

    public function testItWritesWithTheConfiguredEncodingOptions(): void
    {
        $response = new RowsResponse(rows: [['id' => 'é']]);

        $event = $this->viewEvent($response, '/checks');
        $this->subscriber()($event);
        self::assertSame(json_encode(['rows' => [['id' => 'é']], 'summary' => null]), $event->getResponse()?->getContent(), 'Unicode escaped: JsonResponse\'s defaults, as TypeBridgeResponseSubscriber writes');

        $event = $this->viewEvent($response, '/checks');
        $this->subscriber(encodingOptions: \JSON_UNESCAPED_UNICODE)($event);
        self::assertSame(json_encode(['rows' => [['id' => 'é']], 'summary' => null], \JSON_UNESCAPED_UNICODE), $event->getResponse()?->getContent());
    }

    public function testItLeavesANoContentResponseAndAnythingElseAlone(): void
    {
        foreach ([new NoContentResponse(), ['plain' => 'array']] as $result) {
            $event = $this->viewEvent($result, '/checks?include=nothing');

            $this->subscriber()($event);

            self::assertNull($event->getResponse());
        }
    }

    private static function response(): RowsResponse
    {
        return new RowsResponse(rows: [[
            'id' => 'a',
            'thing' => new Ref(Thing::class, 'thing-1'),
            'status' => new EnumCase(CheckStatus::Ok),
            'debug' => new Optional(static fn (): bool => true),
        ]]);
    }

    private function subscriber(string $enumFormatHeader = 'X-Enum-Format', int $encodingOptions = JsonResponse::DEFAULT_ENCODING_OPTIONS): IncludeResponseSubscriber
    {
        return new IncludeResponseSubscriber(
            new IncludeResolver(new RefRegistry([new ThingNormalizer()])),
            new StatusCodeResolver(),
            new DefaultValidationErrorResponseFactory(),
            $enumFormatHeader,
            $encodingOptions,
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function viewEvent(mixed $controllerResult, string $uri, array $server = []): ViewEvent
    {
        return new ViewEvent(
            new StubHttpKernel(),
            Request::create($uri, server: $server),
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult,
        );
    }
}
