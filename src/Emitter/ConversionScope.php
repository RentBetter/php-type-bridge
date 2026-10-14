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
     * @param array<string, array<string, string>> $foreignAliases the module's local name for a
     *        symbol imported from another domain, where the plain name collides with a local one
     * @param bool $fileScoped whether the scope is one file's — every alias that file declares or
     *        imports is among `$importedSymbols` — so a name carrying its class names that class,
     *        whatever the emitting module declares under the same name
     */
    public function __construct(
        public string $domain,
        public array $importedSymbols = [],
        public array $foreignAliases = [],
        public bool $fileScoped = false,
    ) {}
}
