<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

use RuntimeException;

/**
 * How a project marks the parts of a response that are only sent when a request asks for
 * them (`?include=…`, `?expand=…`), so the generated types can say so.
 *
 * - `types`: generics a shape wraps such a key's type in — `debug: included<mixed>`. TypeBridge
 *   emits the key as optional, since it is absent unless asked for. When the PHP key is
 *   required, it is also present whenever it is asked for, and the shape's `{Shape}Included`
 *   union lists it; an optional one (`documentation?: included<string>`) may still be absent.
 * - `sideLoadAttribute`: the attribute on a response property that is a side-loaded collection.
 *   Asked for, it is always there — an empty list when nothing is referenced — so it is listed
 *   in the response's `{Response}Included` union.
 * - `refTypes`: generics a shape writes a reference to a related record with — `definition:
 *   ref<DefinitionData>`. It is sent as the record's id, and as the record itself when the
 *   request expands its path. TypeBridge emits it as `Ref<DefinitionData>`, which
 *   `WithExpands<T, P>` swaps for the record at the paths a request expanded.
 */
final readonly class IncludeConvention
{
    private const string GENERIC_NAME = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

    /**
     * @param list<string> $types
     * @param class-string|null $sideLoadAttribute
     * @param list<string> $refTypes
     */
    public function __construct(
        public array $types = [],
        public ?string $sideLoadAttribute = null,
        public array $refTypes = [],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $unknownKeys = array_diff(array_keys($config), ['types', 'sideLoadAttribute', 'refTypes']);
        if ([] !== $unknownKeys) {
            throw new RuntimeException(\sprintf(
                'Unknown TypeBridge "includes" config keys: %s. Allowed: types, sideLoadAttribute, refTypes.',
                implode(', ', $unknownKeys),
            ));
        }

        $attribute = $config['sideLoadAttribute'] ?? null;
        if (null !== $attribute && (!\is_string($attribute) || !class_exists($attribute))) {
            throw new RuntimeException('TypeBridge config key "includes.sideLoadAttribute" must name an attribute class.');
        }

        return new self(
            self::genericNames($config, 'types', 'included'),
            $attribute,
            self::genericNames($config, 'refTypes', 'ref'),
        );
    }

    public function isIncludeType(string $name): bool
    {
        return \in_array($name, $this->types, true);
    }

    public function isRefType(string $name): bool
    {
        return \in_array($name, $this->refTypes, true);
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private static function genericNames(array $config, string $key, string $example): array
    {
        $names = $config[$key] ?? [];
        if (!\is_array($names) || !array_is_list($names)) {
            throw new RuntimeException(\sprintf('TypeBridge config key "includes.%s" must be a list of generic names.', $key));
        }
        foreach ($names as $name) {
            if (!\is_string($name) || 1 !== preg_match(self::GENERIC_NAME, $name)) {
                throw new RuntimeException(\sprintf('TypeBridge config key "includes.%s" must list generic names, such as "%s".', $key, $example));
            }
        }

        return $names;
    }
}
