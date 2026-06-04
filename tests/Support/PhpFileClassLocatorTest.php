<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\PhpFileClassLocator;

final class PhpFileClassLocatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/tb-locator-' . bin2hex(random_bytes(6));
        mkdir($this->dir, recursive: true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function test_ignores_class_like_keywords_in_comments(): void
    {
        $file = $this->write('Thing.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Acme\Widgets;

            /**
             * Discovers enum Foo and the class Bar convention for TypeBridge.
             */
            final class Thing {}
            PHP);

        self::assertSame(['Acme\\Widgets\\Thing' => $file], (new PhpFileClassLocator())->classesIn($this->dir));
    }

    public function test_extracts_enum_names(): void
    {
        $file = $this->write('Status.php', <<<'PHP'
            <?php

            namespace Acme\Status;

            enum Status: string
            {
                case OPEN = 'open';
            }
            PHP);

        self::assertSame(['Acme\\Status\\Status' => $file], (new PhpFileClassLocator())->classesIn($this->dir));
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }
}
