<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter\Builtin;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Built-in convention: a class implementing {@see ApiResponse} emits as a response
 * interface (or `= null` for an empty 204). Referenced — emitted when collected.
 */
#[AsTypeBridgeEmitter('responses', mode: EmitMode::Referenced)]
final class ResponseClassEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(ApiResponse::class) && $class->isInstantiable();
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        $response = $context->responseFor($class->getName());
        if (null === $response) {
            return new EmittedType($context->domain, []);
        }

        return new EmittedType($context->domain, [new EmittedBlock(30, '// Responses', $this->render($response, $context))]);
    }

    private function render(CollectedApiResponseClass $response, EmitContext $context): string
    {
        // A 204 is sent with no body at all; the collector gives it no properties.
        if (204 === $response->status && [] === $response->properties) {
            $name = $context->names->responseDeclarationName($response);
            $lines = [\sprintf('export type %s = null;', $name)];
            // One that declares properties of its own was an interface of them, under the interface
            // prefix: the name stays a while, deprecated, so code that imports it still compiles.
            $was = $context->names->deprecatedResponseName($response);
            if (null !== $was) {
                $lines[] = \sprintf('/** @deprecated A 204 is sent with no body; use %s. */', $name);
                $lines[] = \sprintf('export type %s = %s;', $was, $name);
            }

            return implode("\n", $lines);
        }

        $name = $context->names->responseDeclarationName($response);
        $lines = [\sprintf('export interface %s {', $name)];
        foreach ($response->properties as $property) {
            try {
                // Converted in the scope of the file it is written in: the imports of that file it uses.
                $type = $context->convert($property->parsed, $context->scopeFor($property->imports, $property->parsed, $property->writtenInDomain));
            } catch (RuntimeException $e) {
                throw self::failedProperty($response, $property, $e);
            }
            $lines[] = \sprintf('  %s%s: %s;', $property->name, $property->optional || $context->converter->isIncluded($property->parsed) ? '?' : '', $type);
        }
        $lines[] = '}';

        $included = self::includedProperties($response, $context->converter->includes);
        if ([] !== $included) {
            $lines[] = '';
            $lines[] = \sprintf('export type %s%s = %s;', $name, TypeScriptEmitter::INCLUDED_SUFFIX, implode(' | ', array_map(static fn (string $property): string => "'" . $property . "'", $included)));
        }

        return implode("\n", $lines);
    }

    /**
     * A failure to emit a property's type, naming the response and the property, wherever it is
     * written — a base's or a trait's, in the source tree or a library's — and what it is typed.
     */
    public static function failedProperty(CollectedApiResponseClass $response, CollectedResponseProperty $property, RuntimeException $failure): RuntimeException
    {
        return new RuntimeException(\sprintf(
            'The %s property %s::$%s is typed `%s`, which can\'t be emitted: %s',
            $response->className,
            $property->writtenIn ?? $response->className,
            $property->name,
            $property->rawType,
            $failure->getMessage(),
        ), previous: $failure);
    }

    /**
     * The properties of a response that are present whenever the request asks for them: a side-load
     * (an empty list when nothing is referenced), or an included value whose PHP property is not
     * nullable.
     *
     * @return list<string>
     */
    public static function includedProperties(CollectedApiResponseClass $response, IncludeConvention $includes): array
    {
        $included = [];
        foreach ($response->properties as $property) {
            if (self::isSideLoad($response->className, $property->name, $includes) || ($includes->isIncluded($property->parsed) && !$property->optional)) {
                $included[] = $property->name;
            }
        }

        return $included;
    }

    private static function isSideLoad(string $class, string $property, IncludeConvention $includes): bool
    {
        $attribute = $includes->sideLoadAttribute;
        if (null === $attribute || !class_exists($class) || !property_exists($class, $property)) {
            return false;
        }

        return [] !== (new ReflectionProperty($class, $property))->getAttributes($attribute);
    }
}
