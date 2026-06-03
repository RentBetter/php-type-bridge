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
     * Relative import path from one domain's module to another's.
     */
    public function getRelativeImportPath(string $fromDomain, string $toDomain): string
    {
        return '../' . $this->dirName($toDomain) . '/genTypes';
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

    private function dirName(string $domain): string
    {
        return match ($this->structure->segmentCase) {
            SegmentCase::AsIs => $domain,
            SegmentCase::PerSegmentLcFirst => implode('/', array_map(lcfirst(...), explode('/', str_replace('\\', '/', $domain)))),
        };
    }
}
