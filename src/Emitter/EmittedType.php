<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

/**
 * The result of an emitter rendering a class (or a common module).
 *
 * Emitters never write files, banners, or resolved import paths — they return
 * ordered {@see EmittedBlock}s and logical {@see EmitImport}s, and the
 * orchestrator owns assembly, import-path resolution, and the collision guard.
 *
 * `domain` is the target module; the empty string denotes the root common module.
 */
final readonly class EmittedType
{
    /**
     * @param list<EmittedBlock> $blocks
     * @param list<EmitImport>   $imports
     */
    public function __construct(
        public string $domain,
        public array $blocks,
        public array $imports = [],
    ) {}
}
