<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\Builtin\EndpointContractEmitter;
use PTGS\TypeBridge\Emitter\Builtin\ResponseClassEmitter;
use PTGS\TypeBridge\Emitter\Builtin\SelfShapeEmitter;
use PTGS\TypeBridge\Emitter\Builtin\ValueOfEnumEmitter;
use ReflectionClass;
use RuntimeException;

/**
 * The set of emitters available to a generation run. Plain PHP (no Symfony DI),
 * so it is reachable from both the CLI command and the PHPStan extension.
 *
 * Built-ins are listed by {@see self::default()}; a consuming application adds its
 * own emitters by scanning its source for #[AsTypeBridgeEmitter] via
 * {@see self::fromAttributeScan()}.
 */
final readonly class EmitterRegistry
{
    /**
     * @param list<RegisteredEmitter> $emitters
     */
    public function __construct(private array $emitters) {}

    public static function default(): self
    {
        return new self([
            new RegisteredEmitter(new ValueOfEnumEmitter(), 'value-of', 0, EmitMode::Referenced),
            new RegisteredEmitter(new SelfShapeEmitter(), '_self', 0, EmitMode::Referenced),
            new RegisteredEmitter(new ResponseClassEmitter(), 'responses', 0, EmitMode::Referenced),
            new RegisteredEmitter(new EndpointContractEmitter(), 'endpoint-contracts', 0, EmitMode::Referenced),
        ]);
    }

    /**
     * Merges emitters discovered via #[AsTypeBridgeEmitter] on the given candidate
     * classes onto a base registry (the built-ins by default).
     *
     * @param list<string> $candidateClasses
     */
    public static function fromAttributeScan(array $candidateClasses, ?self $base = null): self
    {
        $base ??= self::default();
        $emitters = $base->emitters;

        foreach ($candidateClasses as $candidate) {
            if (!class_exists($candidate)) {
                continue;
            }

            $reflection = new ReflectionClass($candidate);
            $attributes = $reflection->getAttributes(AsTypeBridgeEmitter::class);
            if ([] === $attributes) {
                continue;
            }

            if (!$reflection->implementsInterface(TypeEmitter::class)) {
                throw new RuntimeException(\sprintf(
                    '"%s" is marked #[AsTypeBridgeEmitter] but does not implement %s.',
                    $candidate,
                    TypeEmitter::class,
                ));
            }

            $meta = $attributes[0]->newInstance();
            /** @var TypeEmitter $instance */
            $instance = $reflection->newInstance();
            $emitters[] = new RegisteredEmitter($instance, $meta->convention, $meta->priority, $meta->mode);
        }

        return new self($emitters);
    }

    public function byConvention(string $convention): TypeEmitter
    {
        foreach ($this->emitters as $registered) {
            if ($registered->convention === $convention) {
                return $registered->emitter;
            }
        }

        throw new RuntimeException(\sprintf('No TypeBridge emitter registered for convention "%s".', $convention));
    }

    /**
     * @return list<RegisteredEmitter>
     */
    public function discovered(): array
    {
        return array_values(array_filter(
            $this->emitters,
            static fn(RegisteredEmitter $registered): bool => EmitMode::Discovered === $registered->mode,
        ));
    }

    /**
     * The single emitter that owns a class for discovery-driven routing: the
     * highest-priority claimant. Returns null when no emitter claims it; throws
     * when two claim it at equal priority.
     *
     * @param ReflectionClass<object> $class
     */
    public function ownerFor(ReflectionClass $class): ?RegisteredEmitter
    {
        $claiming = array_values(array_filter(
            $this->emitters,
            static fn(RegisteredEmitter $registered): bool => $registered->emitter->claims($class),
        ));

        if ([] === $claiming) {
            return null;
        }

        usort($claiming, static fn(RegisteredEmitter $a, RegisteredEmitter $b): int => $b->priority <=> $a->priority);

        if (\count($claiming) > 1 && $claiming[0]->priority === $claiming[1]->priority) {
            throw new RuntimeException(\sprintf(
                'Multiple TypeBridge emitters claim "%s" at priority %d: %s.',
                $class->getName(),
                $claiming[0]->priority,
                implode(', ', array_map(static fn(RegisteredEmitter $r): string => $r->convention, $claiming)),
            ));
        }

        return $claiming[0];
    }
}
