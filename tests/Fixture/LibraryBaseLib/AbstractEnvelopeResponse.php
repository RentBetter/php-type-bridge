<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's base response whose properties have only their native types, and no docblock a
 * project could add one to.
 */
abstract class AbstractEnvelopeResponse
{
    public array $details = [];

    public ?string $hint = null;
}
