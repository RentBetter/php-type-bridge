<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Support;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use ReflectionAttribute;
use ReflectionMethod;

final class ApiMethodInspector
{
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
                $declaredResponses = array_values(array_filter(
                    $responses,
                    static fn (mixed $value): bool => \is_string($value) && '' !== $value,
                ));
            }
        }

        return new InspectedApiMethod(
            path: $path,
            httpMethods: $httpMethods,
            hasApiRequest: [] !== $method->getAttributes(ApiRequest::class),
            hasApiResponses: [] !== $responsesAttributes,
            declaredResponses: $declaredResponses,
        );
    }

    private function isRouteAttribute(ReflectionAttribute $attribute): bool
    {
        $name = $attribute->getName();

        return 'Symfony\\Component\\Routing\\Attribute\\Route' === $name
            || str_ends_with($name, '\\Route')
            || 'Route' === $name;
    }
}
