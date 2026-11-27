<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http;

use PTGS\TypeBridge\Attribute\Metadata;
use ReflectionClass;
use ReflectionProperty;

/**
 * What a response sends: each of its public properties under the key it is sent as — its name, or
 * for a {@see Metadata} property its name behind a `$`. The one place that decides the key, so what
 * is written out and what is published agree.
 */
final class ResponseFields
{
    /** @var array<class-string, array<string, string>> each class's metadata properties, name to key */
    private static array $metadata = [];

    /**
     * A response's fields, in the order it declares them.
     *
     * @return array<array-key, mixed>
     */
    public static function of(object $response): array
    {
        $fields = get_object_vars($response);
        $metadata = self::$metadata[$response::class] ??= self::metadataOf($response::class);
        if ([] === $metadata) {
            return $fields;
        }

        $keyed = [];
        foreach ($fields as $name => $value) {
            $keyed[$metadata[$name] ?? $name] = $value;
        }

        return $keyed;
    }

    /**
     * The key a response property is sent under.
     */
    public static function key(ReflectionProperty $property): string
    {
        return self::isMetadata($property) ? Metadata::key($property->getName()) : $property->getName();
    }

    public static function isMetadata(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(Metadata::class);
    }

    /**
     * @param class-string $class
     *
     * @return array<string, string>
     */
    private static function metadataOf(string $class): array
    {
        $metadata = [];
        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && self::isMetadata($property)) {
                $metadata[$property->getName()] = self::key($property);
            }
        }

        return $metadata;
    }
}
