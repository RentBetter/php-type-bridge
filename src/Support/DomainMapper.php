<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

/**
 * Maps PHP namespaces to domain directories and output file paths.
 */
final readonly class DomainMapper
{
    public function __construct(
        private readonly string $outputDir,
        private readonly bool $lcFirstDirs = false,
    ) {}

    /**
     * Gets the output file path for a domain's generated types.
     */
    public function getOutputPath(string $domain): string
    {
        return $this->outputDir . '/' . $this->dirName($domain) . '/genTypes.ts';
    }

    /**
     * Gets the relative import path from one domain to another.
     */
    public function getRelativeImportPath(string $fromDomain, string $toDomain): string
    {
        return '../' . $this->dirName($toDomain) . '/genTypes';
    }

    private function dirName(string $domain): string
    {
        return $this->lcFirstDirs ? lcfirst($domain) : $domain;
    }
}
