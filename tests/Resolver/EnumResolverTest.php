<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Tests\InvalidFixture\InvalidFixtureProject;
use RuntimeException;

final class EnumResolverTest extends TestCase
{
    public function test_it_throws_when_two_enums_share_a_short_name(): void
    {
        $srcDir = InvalidFixtureProject::srcDir('EnumShortNameCollision');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Enum short-name collision: "Status"');

        (new EnumResolver())->scanDirectory($srcDir);
    }
}
