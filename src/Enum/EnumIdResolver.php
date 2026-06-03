<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Enum;

use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

/**
 * Resolves the list of id literals for an enum's cases, in declaration order,
 * according to an {@see EnumIdSource}.
 *
 * Execution (method / map strategies) is fail-do-not-guess: the referenced method
 * must be public, parameterless, and declared `: string`, and must actually return
 * a string at runtime — otherwise a {@see RuntimeException} is raised rather than
 * silently emitting a wrong literal. This resolver is used only by opt-in emitters;
 * TypeBridge core never executes enum methods.
 */
final class EnumIdResolver
{
    /**
     * @param ReflectionEnum<\UnitEnum> $enum
     * @return list<string>
     */
    public function resolve(ReflectionEnum $enum, EnumIdSource $source): array
    {
        if (null !== $source->method) {
            return $this->viaMethod($enum, $source->method);
        }

        if (null !== $source->map) {
            return $this->viaMap($enum, $source->map);
        }

        return $this->viaReflection($enum, $source->source);
    }

    /**
     * @param ReflectionEnum<\UnitEnum> $enum
     * @return list<string>
     */
    private function viaMethod(ReflectionEnum $enum, string $method): array
    {
        if (!$enum->hasMethod($method)) {
            throw new RuntimeException(\sprintf('Enum "%s" has no method "%s()" referenced by its EnumIdSource.', $enum->getName(), $method));
        }

        $reflectionMethod = $enum->getMethod($method);
        $this->assertStringContract($enum->getName(), $reflectionMethod);

        $ids = [];
        foreach ($enum->getCases() as $case) {
            $value = $reflectionMethod->invoke($case->getValue());
            $ids[] = $this->assertString(\sprintf('%s::%s()', $enum->getName(), $method), $value);
        }

        return $ids;
    }

    /**
     * @param ReflectionEnum<\UnitEnum> $enum
     * @param array{class-string, non-empty-string} $map
     * @return list<string>
     */
    private function viaMap(ReflectionEnum $enum, array $map): array
    {
        [$class, $method] = $map;

        $reflectionMethod = new ReflectionMethod($class, $method);
        if (!$reflectionMethod->isStatic()) {
            throw new RuntimeException(\sprintf('EnumIdSource map method "%s::%s()" must be static.', $class, $method));
        }
        $this->assertStringContract($class, $reflectionMethod, expectParameters: 1);

        $ids = [];
        foreach ($enum->getCases() as $case) {
            $value = $reflectionMethod->invoke(null, $case->getValue());
            $ids[] = $this->assertString(\sprintf('%s::%s()', $class, $method), $value);
        }

        return $ids;
    }

    /**
     * @param ReflectionEnum<\UnitEnum> $enum
     * @return list<string>
     */
    private function viaReflection(ReflectionEnum $enum, EnumIdSourceMode $mode): array
    {
        $ids = [];
        foreach ($enum->getCases() as $case) {
            $ids[] = match ($mode) {
                EnumIdSourceMode::CaseName => $case->getName(),
                EnumIdSourceMode::BackingValue => $this->backingValue($enum, $case),
                EnumIdSourceMode::PrefixedCaseName => $this->prefix($enum) . $case->getName(),
            };
        }

        return $ids;
    }

    private function assertStringContract(string $owner, ReflectionMethod $method, int $expectParameters = 0): void
    {
        $returnType = $method->getReturnType();

        if (
            !$method->isPublic()
            || $method->getNumberOfRequiredParameters() > $expectParameters
            || $method->getNumberOfParameters() > $expectParameters
            || !$returnType instanceof ReflectionNamedType
            || $returnType->allowsNull()
            || 'string' !== $returnType->getName()
        ) {
            throw new RuntimeException(\sprintf(
                'EnumIdSource method "%s::%s()" must be public, accept %d argument(s), and be declared `: string`.',
                $owner,
                $method->getName(),
                $expectParameters,
            ));
        }
    }

    private function assertString(string $context, mixed $value): string
    {
        if (!\is_string($value)) {
            throw new RuntimeException(\sprintf('%s must return a string, got %s.', $context, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param ReflectionEnum<\UnitEnum> $enum
     */
    private function backingValue(ReflectionEnum $enum, ReflectionEnumUnitCase $case): string
    {
        if (!$case instanceof ReflectionEnumBackedCase) {
            throw new RuntimeException(\sprintf('Enum "%s" is not backed; BackingValue id source is unavailable.', $enum->getName()));
        }

        $value = $case->getBackingValue();
        if (!\is_string($value)) {
            throw new RuntimeException(\sprintf('Enum "%s" must be string-backed for the BackingValue id source.', $enum->getName()));
        }

        return $value;
    }

    /**
     * @param ReflectionEnum<\UnitEnum> $enum
     */
    private function prefix(ReflectionEnum $enum): string
    {
        if ($enum->hasConstant('ID_PREFIX')) {
            $prefix = $enum->getConstant('ID_PREFIX');
            if (\is_string($prefix)) {
                return $prefix;
            }
        }

        return '';
    }
}
