<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

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
        if (enum_exists($type->ownerClass)) {
            $name = $this->naming->enumShapeName($this->enumResolver->getShortName($type->ownerClass));
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

    public function enumName(string $enumClass): string
    {
        return $this->naming->enumValueName($this->enumResolver->getShortName($enumClass));
    }
}
