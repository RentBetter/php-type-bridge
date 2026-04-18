<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Resolver;

use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionEnum;
use RuntimeException;

final class EnumResolver
{
    /** @var array<class-string, list<string>> */
    private array $cache = [];

    /** @var array<string, class-string> */
    private array $classMap = [];

    /** @var array<class-string, string> */
    private array $domainMap = [];

    public function __construct(
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
    ) {}

    public function scanDirectory(string $srcDir): void
    {
        foreach ($this->classLocator->classesIn($srcDir) as $className => $file) {
            if (!enum_exists($className)) {
                continue;
            }

            $reflection = new ReflectionEnum($className);
            if (!$reflection->isBacked() || 'string' !== (string) $reflection->getBackingType()) {
                continue;
            }

            $this->classMap[$reflection->getShortName()] = $className;
            $this->domainMap[$className] = $this->domainGuesser->guess($srcDir, $file);
        }
    }

    /**
     * @return list<string>
     */
    public function resolve(string $className): array
    {
        $fqcn = $this->resolveFqcn($className);
        if (isset($this->cache[$fqcn])) {
            return $this->cache[$fqcn];
        }

        if (!enum_exists($fqcn)) {
            throw new RuntimeException(\sprintf('Enum class "%s" was not found.', $className));
        }

        $reflection = new ReflectionEnum($fqcn);
        if (!$reflection->isBacked() || 'string' !== (string) $reflection->getBackingType()) {
            throw new RuntimeException(\sprintf('Enum "%s" must be string-backed.', $fqcn));
        }

        $values = [];
        foreach ($reflection->getCases() as $case) {
            $values[] = $case->getBackingValue();
        }

        $this->cache[$fqcn] = $values;

        return $values;
    }

    public function resolveFqcn(string $className): string
    {
        return $this->classMap[$className] ?? $className;
    }

    public function getDomain(string $className): string
    {
        $fqcn = $this->resolveFqcn($className);

        return $this->domainMap[$fqcn] ?? 'Common';
    }

    public function getShortName(string $className): string
    {
        $fqcn = $this->resolveFqcn($className);
        $position = strrpos($fqcn, '\\');
        if (false === $position) {
            return $fqcn;
        }

        return substr($fqcn, $position + 1);
    }
}
