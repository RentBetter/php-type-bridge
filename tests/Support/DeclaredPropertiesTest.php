<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\DeclaredProperties;

final class DeclaredPropertiesTest extends TestCase
{
    public function test_every_property_a_declaration_lists_is_declared(): void
    {
        // `public $a, $b;` declares both, a default holding a comma included; a promoted param is a
        // property, another param is not, nor a method's static variable.
        $source = <<<'PHP'
            <?php

            final class Holder
            {
                public $a, $b;
                public int $c = 1, $d = 2;
                public array $e = [1, 2], $f;

                public function __construct(public int $g, int $h)
                {
                }

                public function run(int $i, int $j): int
                {
                    static $k, $l;

                    return $i;
                }
            }
            PHP;

        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g'], array_keys(DeclaredProperties::inSource($source, 3)));
    }
}
