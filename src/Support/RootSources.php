<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PTGS\TypeBridge\Emitter\EmitImport;
use PTGS\TypeBridge\Emitter\EmittedType;
use ReflectionClass;

/**
 * The classes under the configured root sources, whose types the root module declares. The
 * collectors place what they collect through {@see DomainGuesser}; an emitter that places its own
 * declarations — a discovered emitter, and the symbols it publishes for `id-of<>` and class types —
 * is re-homed here by the same rule, so a class's types land in one module whichever code emits them.
 */
final readonly class RootSources
{
    public function __construct(
        private DomainGuesser $domainGuesser = new DomainGuesser(),
        private string $sourceDir = '',
    ) {}

    /**
     * @param ReflectionClass<object> $class
     */
    public function holds(ReflectionClass $class): bool
    {
        $file = $class->getFileName();
        if ('' === $this->sourceDir || false === $file || !str_starts_with($file, $this->sourceDir)) {
            return false;
        }

        return '' === $this->domainGuesser->guess($this->sourceDir, $file);
    }

    /**
     * @param ReflectionClass<object> $class
     */
    public function place(EmitImport $symbol, ReflectionClass $class): EmitImport
    {
        return $this->holds($class) ? new EmitImport('', $symbol->canonicalName) : $symbol;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    public function placeEmitted(EmittedType $emitted, ReflectionClass $class): EmittedType
    {
        return $this->holds($class) ? new EmittedType('', $emitted->blocks, $emitted->imports) : $emitted;
    }
}
