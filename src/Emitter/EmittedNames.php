<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Attribute\ValueOfName;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Resolver\EnumResolver;

/**
 * Derives the emitted TypeScript symbol name for a collected type, response, or
 * enum. Stateless and domain-independent, so it is shared by both the orchestrator
 * (when building the symbol map / collision guard) and the emitters (when rendering
 * declarations) — guaranteeing the two agree on names.
 */
final readonly class EmittedNames
{
    public function __construct(
        private TypeScriptNaming $naming,
        private EnumResolver $enumResolver,
    ) {}

    public function typeDeclarationName(CollectedType $type): string
    {
        $name = $type->name;
        // Only its _self is the enum's shape; any other alias it declares keeps its own name. The
        // shape takes the enum's own name when its value union has been named something else.
        if ($type->isSelf && enum_exists($type->ownerClass)) {
            $shortName = $this->enumResolver->getShortName($type->ownerClass);
            $name = ValueOfName::shapeName($type->ownerClass, $shortName, $this->naming->enumShapeName($shortName));
        }

        if ($type->parsed instanceof ShapeType || $type->parsed instanceof IntersectionType) {
            return $this->naming->interfaceName($name);
        }

        return $name;
    }

    public function responseDeclarationName(CollectedApiResponseClass $response): string
    {
        if (204 === $response->status && [] === $response->properties) {
            return $response->name;
        }

        return $this->naming->interfaceName($response->name);
    }

    /**
     * The name a 204 that declares properties of its own kept, deprecated, beside its own: it was an
     * interface of them, under the interface prefix, and code importing it still compiles. Null for
     * any other response, and where the prefix leaves its name as it was.
     */
    public function deprecatedResponseName(CollectedApiResponseClass $response): ?string
    {
        $was = $this->naming->interfaceName($response->name);

        return 204 === $response->status && [] === $response->properties && $response->declaresProperties && $was !== $response->name ? $was : null;
    }

    /**
     * The module an enum is declared in.
     */
    public function enumDomain(string $enumClass): string
    {
        return $this->enumResolver->getDomain($enumClass);
    }

    public function enumName(string $enumClass): string
    {
        return ValueOfName::of($this->enumResolver->resolveFqcn($enumClass))->name
            ?? $this->naming->enumValueName($this->enumResolver->getShortName($enumClass));
    }
}
