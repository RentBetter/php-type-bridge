<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http;

use PTGS\TypeBridge\Contract\ApiErrorResponse;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use ReflectionClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts typed ApiErrorResponse throwables into JsonResponse.
 *
 * Status is inferred from the throwable's HTTP marker interface; the public properties
 * of the throwable ARE the JSON body (via get_object_vars()). Propagation is stopped so
 * the framework's default exception handling does not also run.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -1)]
final readonly class TypeBridgeThrowableListener
{
    public function __construct(
        private StatusCodeResolver $resolver,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof ApiErrorResponse) {
            return;
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($throwable);
        $status = $this->resolver->resolve($reflection);
        $event->setResponse(new JsonResponse(get_object_vars($throwable), $status));
        $event->stopPropagation();
    }
}
