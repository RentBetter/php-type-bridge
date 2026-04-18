<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Config;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use RuntimeException;

final class TypeScriptNamingTest extends TestCase
{
    public function test_it_rejects_unknown_keys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown TypeScript naming config keys: unknownKey.');

        TypeScriptNaming::fromArray([
            'unknownKey' => 'value',
        ]);
    }

    public function test_it_requires_string_values(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming config key "interfacePrefix" must be a string.');

        TypeScriptNaming::fromArray([
            'interfacePrefix' => ['I'],
        ]);
    }
}
