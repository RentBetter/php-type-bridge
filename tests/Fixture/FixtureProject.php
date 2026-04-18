<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture;

final class FixtureProject
{
    public static function srcDir(): string
    {
        return __DIR__ . '/Fixtures';
    }
}
