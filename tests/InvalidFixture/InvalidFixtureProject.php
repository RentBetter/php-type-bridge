<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture;

final class InvalidFixtureProject
{
    public static function srcDir(string $scenario): string
    {
        return __DIR__ . '/Fixtures/' . $scenario;
    }
}
