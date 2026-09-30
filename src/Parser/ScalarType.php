<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ScalarType extends ParsedType
{
    /**
     * PHPStan's refinements of a scalar, each with the scalar it refines. They narrow the value
     * in ways TypeScript cannot state (`positive-int` is still just a number there), so they
     * emit as their base; the spelling is kept so a shape renders back the way it was written.
     * Longer spellings first, so none is read as a prefix of another.
     */
    public const array REFINEMENTS = [
        'non-positive-int' => 'int',
        'non-negative-int' => 'int',
        'non-zero-int' => 'int',
        'positive-int' => 'int',
        'negative-int' => 'int',
        'non-empty-string' => 'string',
        'non-falsy-string' => 'string',
        'truthy-string' => 'string',
        'numeric-string' => 'string',
        'literal-string' => 'string',
        'lowercase-string' => 'string',
        'uppercase-string' => 'string',
        'class-string' => 'string',
        'interface-string' => 'string',
        'enum-string' => 'string',
        'array-key' => 'array-key',
        'scalar' => 'scalar',
    ];

    public function __construct(
        public string $type, // 'string', 'int', 'float', 'bool', 'mixed', 'numeric', 'null', or a refinement
    ) {}

    /**
     * The scalar this type is, refinements reduced to what they refine: `positive-int` is `int`.
     */
    public function base(): string
    {
        return self::REFINEMENTS[$this->type] ?? $this->type;
    }
}
