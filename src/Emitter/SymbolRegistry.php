<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use RuntimeException;

/**
 * Resolves a domain-local logical type name to its emitted TypeScript symbol.
 *
 * Holds the per-domain symbol maps built once per emit run and owns the
 * unknown-symbol guard. Extracted from TypeScriptEmitter so emitters can resolve
 * cross-references through a shared, stateless service.
 */
final readonly class SymbolRegistry
{
    /**
     * @param array<string, array<string, string>> $maps domain => (logicalName => emittedName)
     * @param list<string> $shared the names every module declares for itself where it uses them —
     *        the config's type aliases — so a name of these is the emitting module's own wherever
     *        it is written
     */
    public function __construct(
        private array $maps,
        private array $shared = [],
    ) {}

    public function has(string $domain, string $logicalName): bool
    {
        return isset($this->maps[$domain][$logicalName]);
    }

    public function isShared(string $logicalName): bool
    {
        return \in_array($logicalName, $this->shared, true);
    }

    public function resolve(string $domain, string $logicalName): string
    {
        if (!isset($this->maps[$domain][$logicalName])) {
            throw new RuntimeException(\sprintf(
                'Unknown TypeScript symbol "%s" referenced in domain "%s". Declare it via @phpstan-type, '
                . 'import it via @phpstan-import-type, or implement ApiResponse on the response class.',
                $logicalName,
                $domain,
            ));
        }

        return $this->maps[$domain][$logicalName];
    }
}
