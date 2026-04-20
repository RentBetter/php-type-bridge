<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Resolver;

use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use RuntimeException;

final class EnumResolver
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    /** @var array<string, string> */
    private array $classMap = [];

    /** @var array<string, string> */
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
            $backingType = $reflection->getBackingType();
            if (!$reflection->isBacked() || null === $backingType || 'string' !== $backingType->getName()) {
                continue;
            }

            $shortName = $reflection->getShortName();
            if (!is_string($shortName) || '' === $shortName) {
                continue;
            }

            if (isset($this->classMap[$shortName]) && $this->classMap[$shortName] !== $className) {
                throw new RuntimeException(\sprintf(
                    'Enum short-name collision: "%s" is declared by both "%s" and "%s". '
                    . 'Rename one of the enums so that each short name is unique across the codebase.',
                    $shortName,
                    $this->classMap[$shortName],
                    $className,
                ));
            }

            $this->classMap[$shortName] = $className;
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
        $backingType = $reflection->getBackingType();
        if (!$reflection->isBacked() || null === $backingType || 'string' !== $backingType->getName()) {
            throw new RuntimeException(\sprintf('Enum "%s" must be string-backed.', $fqcn));
        }

        $values = [];
        $cases = $reflection->getCases();
        if (!is_array($cases)) {
            throw new RuntimeException(\sprintf('Enum "%s" cases could not be inspected.', $fqcn));
        }

        foreach ($cases as $case) {
            if ($case instanceof ReflectionEnumBackedCase) {
                $value = $case->getBackingValue();
                if (is_string($value)) {
                    $values[] = $value;
                }
            }
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
