<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

use RuntimeException;

/**
 * How a project marks the parts of a response that are only sent when a request asks for
 * them (`?include=…`), so the generated types can say so.
 *
 * - `types`: generics a shape wraps such a key's type in — `debug: included<mixed>`. TypeBridge
 *   emits the key as optional, since it is absent unless asked for. When the PHP key is
 *   required, it is also present whenever it is asked for, and the shape's `{Shape}Included`
 *   union lists it; an optional one (`documentation?: included<string>`) may still be absent.
 * - `sideLoadAttribute`: the attribute on a response property that is a side-loaded collection.
 *   Asked for, it is always there — an empty list when nothing is referenced — so it is listed
 *   in the response's `{Response}Included` union.
 */
final readonly class IncludeConvention
{
    /**
     * @param list<string> $types
     * @param class-string|null $sideLoadAttribute
     */
    public function __construct(
        public array $types = [],
        public ?string $sideLoadAttribute = null,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $unknownKeys = array_diff(array_keys($config), ['types', 'sideLoadAttribute']);
        if ([] !== $unknownKeys) {
            throw new RuntimeException(\sprintf(
                'Unknown TypeBridge "includes" config keys: %s. Allowed: types, sideLoadAttribute.',
                implode(', ', $unknownKeys),
            ));
        }

        $types = $config['types'] ?? [];
        if (!\is_array($types) || !array_is_list($types)) {
            throw new RuntimeException('TypeBridge config key "includes.types" must be a list of generic names.');
        }
        foreach ($types as $type) {
            if (!\is_string($type) || 1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $type)) {
                throw new RuntimeException('TypeBridge config key "includes.types" must list generic names, such as "included".');
            }
        }

        $attribute = $config['sideLoadAttribute'] ?? null;
        if (null !== $attribute && (!\is_string($attribute) || !class_exists($attribute))) {
            throw new RuntimeException('TypeBridge config key "includes.sideLoadAttribute" must name an attribute class.');
        }

        return new self($types, $attribute);
    }

    public function isIncludeType(string $name): bool
    {
        return \in_array($name, $this->types, true);
    }
}
