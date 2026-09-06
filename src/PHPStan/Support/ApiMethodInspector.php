<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Support;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Routing\RoutePathResolver;
use ReflectionAttribute;
use ReflectionMethod;

final class ApiMethodInspector
{
    /**
     * @param RoutePathResolver|null $routePathResolver resolves the *served* path, which the
     *        method attribute alone does not give: a routing-config `prefix` and any class-level
     *        #[Route] are part of it too. Without one, only the attribute's own path is known, so
     *        an application that prefixes in routing config will look like it has no API routes.
     */
    public function __construct(
        private readonly ?RoutePathResolver $routePathResolver = null,
    ) {}

    /**
     * @param class-string $className
     */
    public function inspect(string $className, string $methodName): ?InspectedApiMethod
    {
        if (!method_exists($className, $methodName)) {
            return null;
        }

        $method = new ReflectionMethod($className, $methodName);

        $path = null;
        $httpMethods = [];
        foreach ($method->getAttributes() as $attribute) {
            if (!$this->isRouteAttribute($attribute)) {
                continue;
            }

            $arguments = $attribute->getArguments();
            $pathArgument = $arguments['path'] ?? ($arguments[0] ?? null);
            if (\is_string($pathArgument)) {
                $path = $pathArgument;
            }

            $methodsArgument = $arguments['methods'] ?? [];
            if (\is_array($methodsArgument)) {
                $httpMethods = array_values(array_unique(array_map(
                    static fn (mixed $value): string => strtoupper((string) $value),
                    array_filter($methodsArgument, static fn (mixed $value): bool => \is_string($value) && '' !== $value),
                )));
            }

            break;
        }

        $declaredResponses = [];
        $responsesAttributes = $method->getAttributes(ApiResponses::class);
        if ([] !== $responsesAttributes) {
            $arguments = $responsesAttributes[0]->getArguments();
            $responses = $arguments['responses'] ?? ($arguments[0] ?? []);
            if (\is_array($responses)) {
                foreach ($responses as $value) {
                    if (\is_string($value) && '' !== $value) {
                        $declaredResponses[] = $value;
                    }
                }
            }
        }

        // The router's answer wins where it has one: it already accounts for the routing-config
        // prefix and any class-level #[Route], which the attribute read above cannot see.
        $resolvedPath = $this->routePathResolver?->pathFor($className, $methodName);

        return new InspectedApiMethod(
            path: $resolvedPath ?? $path,
            httpMethods: $httpMethods,
            hasApiRequest: [] !== $method->getAttributes(ApiRequest::class),
            hasApiResponses: [] !== $responsesAttributes,
            declaredResponses: $declaredResponses,
        );
    }

    /**
     * @param ReflectionAttribute<object> $attribute
     */
    private function isRouteAttribute(ReflectionAttribute $attribute): bool
    {
        $name = $attribute->getName();

        return 'Symfony\\Component\\Routing\\Attribute\\Route' === $name
            || str_ends_with($name, '\\Route')
            || 'Route' === $name;
    }
}
