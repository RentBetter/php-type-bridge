<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

/**
 * Per-declaration context for converting a ParsedType to TypeScript.
 *
 * Replaces the emitter's former mutable `currentDomain` / `currentImportedSymbols`
 * fields so {@see TypeToTsConverter} is stateless and reusable across emitters.
 */
final readonly class ConversionScope
{
    /**
     * @param array<string, string> $importedSymbols local alias for each imported type name
     */
    public function __construct(
        public string $domain,
        public array $importedSymbols = [],
    ) {}
}
