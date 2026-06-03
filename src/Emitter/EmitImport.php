<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

/**
 * A logical cross-module import an emitter needs. The emitter names the target
 * domain and the canonical symbol; the orchestrator resolves the actual import
 * path and any collision alias.
 */
final readonly class EmitImport
{
    public function __construct(
        public string $targetDomain,
        public string $canonicalName,
    ) {}
}
