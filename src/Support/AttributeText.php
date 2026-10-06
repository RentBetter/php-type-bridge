<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;

/**
 * The text a project's documentation attribute holds — property-api's Spec\Api on an action, its
 * Spec\Param on a request property. The text is one of the attribute's properties, a string or a
 * list of strings (the description in paragraphs) joined with a space; `description` unless the
 * project names another.
 *
 * One reader for every such attribute, so a tool and its arguments are described the same way.
 */
final readonly class AttributeText
{
    /**
     * @param string      $attribute   FQCN of the attribute
     * @param string|null $property    the property on it holding the text; `description` when null
     * @param string      $propertyKey the config key that names that property, for errors
     */
    public function __construct(
        public string $attribute,
        public ?string $property,
        private string $propertyKey,
    ) {}

    /**
     * The attribute's text on a method or property, or null when it carries none (or it is blank).
     */
    public function on(ReflectionMethod|ReflectionProperty $target): ?string
    {
        $attributes = $target->getAttributes($this->attribute);
        if ([] === $attributes) {
            return null;
        }

        $reflection = new ReflectionObject($instance = $attributes[0]->newInstance());
        $propertyName = $this->property ?? 'description';
        if (!$reflection->hasProperty($propertyName)) {
            throw new RuntimeException(\sprintf(
                'Attribute #[%s] on "%s" has no property "%s" (%s).',
                $this->attribute,
                $this->where($target),
                $propertyName,
                null === $this->property
                    ? \sprintf('the default; name the right one with the %s config', $this->propertyKey)
                    : \sprintf('named by the %s config', $this->propertyKey),
            ));
        }

        $value = $reflection->getProperty($propertyName)->getValue($instance);
        $parts = [];
        foreach (\is_array($value) ? $value : [$value] as $part) {
            if (!\is_string($part)) {
                throw new RuntimeException(\sprintf(
                    'Attribute #[%s] on "%s": property "%s" must hold a string or a list of strings to be read as a description.',
                    $this->attribute,
                    $this->where($target),
                    $propertyName,
                ));
            }

            $part = trim($part);
            if ('' !== $part) {
                $parts[] = $part;
            }
        }

        return [] === $parts ? null : implode(' ', $parts);
    }

    /**
     * What the attribute is on, as an error names it: `Class::method` or `Class::$property`.
     */
    private function where(ReflectionMethod|ReflectionProperty $target): string
    {
        return \sprintf(
            $target instanceof ReflectionProperty ? '%s::$%s' : '%s::%s',
            $target->getDeclaringClass()->getName(),
            $target->getName(),
        );
    }
}
