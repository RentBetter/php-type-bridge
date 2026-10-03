<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

use Closure;

/**
 * An opt-in value in a normalised shape: its key stays off the wire unless the request's
 * `?include=` names the key's path, and the value is only computed when it does. Made by
 * IncludeMarkers::optional(); resolved or dropped by IncludeResolver. A shape declares such a
 * key as `included<T>`, which PHPStan reads as `T|Optional<T>` (includes.neon).
 *
 * @template T
 */
final readonly class Optional
{
    /**
     * @param Closure(): T $value
     */
    public function __construct(
        private Closure $value,
    ) {}

    /**
     * @return T
     */
    public function resolve(): mixed
    {
        return ($this->value)();
    }
}
