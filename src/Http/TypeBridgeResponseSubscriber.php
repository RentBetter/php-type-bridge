<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http;

use PTGS\TypeBridge\Contract\ApiSuccessResponse;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use ReflectionClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts typed ApiSuccessResponse DTOs returned from controllers into JsonResponse.
 *
 * Status is inferred from the DTO's HTTP marker interface (HttpOk, HttpCreated, etc).
 * HttpNoContent DTOs emit a 204 with an empty body. All other success DTOs serialize
 * their public properties directly as the JSON body via get_object_vars().
 */
#[AsEventListener(event: KernelEvents::VIEW, priority: -1)]
final readonly class TypeBridgeResponseSubscriber
{
    public function __construct(
        private StatusCodeResolver $resolver,
    ) {}

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if (!$result instanceof ApiSuccessResponse) {
            return;
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($result);
        $status = $this->resolver->resolve($reflection);

        if (204 === $status) {
            // No Content: a genuinely empty body, not JsonResponse's "{}" for null data.
            $event->setResponse(new Response(status: 204));

            return;
        }

        $event->setResponse(new JsonResponse(get_object_vars($result), $status));
    }
}
