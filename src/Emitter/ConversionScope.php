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
     * @param string|null $aliasDomain the module whose aliases a bare name answers to, where it is not
     *        `$domain`: the module of the file an inherited property is written in. A name means
     *        there what it means in that module's own files, whichever module emits it
     */
    public function __construct(
        public string $domain,
        public array $importedSymbols = [],
        public array $foreignAliases = [],
        public ?string $aliasDomain = null,
    ) {}
}
