<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\DomainGuesser;

/**
 * A file's domain is the module its types go in: its directories under the source root, to the
 * configured depth.
 */
final class DomainGuesserTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function files(): iterable
    {
        yield 'depth 1 is the top directory' => [1, 'Admin/SystemChecks/Entity/Check.php', 'Admin'];
        yield 'depth 2 is the subdomain' => [2, 'Admin/SystemChecks/Entity/Check.php', 'Admin/SystemChecks'];
        yield 'a file in fewer directories is in all of them' => [2, 'Entity/Currency.php', 'Entity'];
        yield 'a file at the root is its own domain' => [2, 'Kernel.php', 'Kernel.php'];
        yield 'depth 3 stops at the file' => [3, 'Admin/SystemChecks/Check.php', 'Admin/SystemChecks'];
    }

    #[DataProvider('files')]
    public function test_the_domain_is_the_files_directories_to_the_depth(int $depth, string $file, string $domain): void
    {
        self::assertSame($domain, (new DomainGuesser($depth))->guess('/src', '/src/' . $file));
    }

    public function test_the_default_is_the_top_directory(): void
    {
        self::assertSame('Admin', (new DomainGuesser())->guess('/src', '/src/Admin/SystemChecks/Check.php'));
    }

    public function test_a_depth_below_one_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DomainGuesser(0);
    }
}
