<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Resolver\EnumResolver;
use ReflectionClass;
use RuntimeException;

/**
 * Answers what `id-of<Enum>` points at: the module and symbol name of the enum's
 * stable-id union.
 *
 * The answer comes from whichever registered emitter claims the enum and implements
 * {@see EnumIdSymbolEmitter}, so the union is named once, by the code that emits it,
 * and referencing it never emits a second declaration to drift from the first.
 */
final readonly class EnumIdSymbolResolver
{
    public function __construct(
        private EnumResolver $enumResolver,
        private EmitterRegistry $registry,
    ) {}

    public function resolve(string $enumClass): EmitImport
    {
        $fqcn = $this->enumResolver->resolveFqcn($enumClass);
        if (!enum_exists($fqcn)) {
            throw new RuntimeException(\sprintf('`id-of<%s>` does not name an enum.', $enumClass));
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($fqcn);
        $owner = $this->registry->ownerFor($reflection);
        if (null === $owner || !$owner->emitter instanceof EnumIdSymbolEmitter) {
            throw new RuntimeException(\sprintf(
                'No emitter publishes stable ids for "%s", so `id-of<%s>` has nothing to point at. '
                . 'The emitter that claims the enum must implement %s.',
                $fqcn,
                $enumClass,
                EnumIdSymbolEmitter::class,
            ));
        }

        return $owner->emitter->idSymbol($reflection);
    }
}
