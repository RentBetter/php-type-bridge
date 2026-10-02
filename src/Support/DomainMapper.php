<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PTGS\TypeBridge\Config\ImportStrategy;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Config\SegmentCase;

/**
 * Maps domain names to output file paths and import paths, per the configured
 * {@see OutputStructure}.
 */
final readonly class DomainMapper
{
    public function __construct(
        private string $outputDir,
        private OutputStructure $structure = new OutputStructure(),
    ) {}

    /**
     * Output path for a domain's generated module.
     */
    public function getOutputPath(string $domain): string
    {
        return $this->outputDir . '/' . $this->dirName($domain) . '/genTypes.ts';
    }

    /**
     * Output path for the shared root common module.
     */
    public function getRootOutputPath(): string
    {
        return $this->outputDir . '/' . ($this->structure->rootModule ?? 'genTypes.ts');
    }

    /**
     * Whether the output has a shared root module, which what every module shares — the config
     * type aliases, the `WithIncludes` helper — is declared in once.
     */
    public function hasRootModule(): bool
    {
        return null !== $this->structure->rootModule;
    }

    /**
     * Relative import path from one domain's module to another's: up to the directory they share,
     * then down to the target — `../../entity/genTypes` from `http/v2`, `../tasks/genTypes` from
     * `admin/systemChecks` to `admin/tasks`. From the root domain, `''`, it starts in the root
     * module's directory: `./admin/tasks/genTypes`.
     */
    public function getRelativeImportPath(string $fromDomain, string $toDomain): string
    {
        $from = '' === $fromDomain ? $this->rootDirectory() : explode('/', $this->dirName($fromDomain));
        $to = explode('/', $this->dirName($toDomain));

        $shared = 0;
        while (isset($from[$shared], $to[$shared]) && $from[$shared] === $to[$shared]) {
            $shared++;
        }

        $up = \count($from) - $shared;
        $down = \array_slice($to, $shared);

        return (0 === $up ? './' : str_repeat('../', $up)) . ([] === $down ? '' : implode('/', $down) . '/') . 'genTypes';
    }

    /**
     * Import path from a domain's module to the shared root common module.
     */
    public function getRootImportPath(string $fromDomain): string
    {
        if (ImportStrategy::Alias === $this->structure->importStrategy && null !== $this->structure->aliasBase) {
            return $this->structure->aliasBase;
        }

        $depth = substr_count($this->dirName($fromDomain), '/') + 1;

        return str_repeat('../', $depth) . pathinfo($this->structure->rootModule ?? 'genTypes.ts', PATHINFO_FILENAME);
    }

    /**
     * @return list<string> the root module's directory under the output directory, by segment
     */
    private function rootDirectory(): array
    {
        $directory = \dirname($this->structure->rootModule ?? 'genTypes.ts');

        return '.' === $directory ? [] : explode('/', $directory);
    }

    private function dirName(string $domain): string
    {
        return match ($this->structure->segmentCase) {
            SegmentCase::AsIs => $domain,
            SegmentCase::PerSegmentLcFirst => implode('/', array_map(lcfirst(...), explode('/', str_replace('\\', '/', $domain)))),
        };
    }
}
