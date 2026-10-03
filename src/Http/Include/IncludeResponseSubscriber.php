<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

use PTGS\TypeBridge\Contract\ApiSuccessResponse;
use PTGS\TypeBridge\Form\ValidationErrorResponseFactory;
use PTGS\TypeBridge\Http\TypeBridgeResponseSubscriber;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use ReflectionClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serialises a TypeBridge response DTO through IncludeResolver, ahead of
 * {@see TypeBridgeResponseSubscriber}, which would write the DTO's fields as they stand —
 * Optionals and Refs included. A 204 is left to it: there is no body to shape.
 *
 * `include` and `expand` are read from the query string, whatever the method: they shape the
 * response, and a body is the data an action changes. TypeBridge publishes them, from
 * `includes.query` in the TypeBridge config, on every endpoint whose success response has a body,
 * so no action declares them. A request sending the enum-format header (`X-Enum-Format: id` by
 * default) gets its enums as bare ids. A path the response cannot answer is the app's 422, built
 * by its ValidationErrorResponseFactory with the parameter as the error's path.
 *
 * Registered by TypeBridgeBundle only when `type_bridge.includes` is enabled.
 */
#[AsEventListener(event: KernelEvents::VIEW, priority: 0)]
final readonly class IncludeResponseSubscriber
{
    /** The enum-format header's value that asks for bare ids. */
    public const string ENUM_IDS = 'id';

    public function __construct(
        private IncludeResolver $resolver,
        private StatusCodeResolver $statusCodes,
        private ValidationErrorResponseFactory $validationErrors,
        private string $enumFormatHeader = 'X-Enum-Format',
        private int $encodingOptions = JsonResponse::DEFAULT_ENCODING_OPTIONS,
    ) {}

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();
        if (!$result instanceof ApiSuccessResponse) {
            return;
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($result);
        $status = $this->statusCodes->resolve($reflection);
        if (204 === $status) {
            return;
        }

        $request = $event->getRequest();
        try {
            $body = $this->resolver->body(
                $result,
                IncludeTree::parse($request->query->getString('include')),
                IncludeTree::parseExpand($request->query->getString('expand')),
                enumIds: self::ENUM_IDS === $request->headers->get($this->enumFormatHeader),
            );
        } catch (InvalidIncludeException $invalid) {
            throw $this->validationErrors->create([['path' => $invalid->parameter, 'message' => $invalid->getMessage()]]);
        }

        $response = new JsonResponse(null, $status);
        $response->setEncodingOptions($this->encodingOptions);
        $event->setResponse($response->setData($body));
    }
}
