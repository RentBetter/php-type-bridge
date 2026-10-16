<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Model\RowSource;
use PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Response\ShowRowResponse;

/**
 * What a class's file says its types mean, as every collector reads it.
 */
final class ClassFileTypesTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/PhpStanOnlyImportedFixtures';

    public function test_an_import_of_an_alias_kept_for_php_stan_is_set_aside_with_why(): void
    {
        $classFiles = (new PhpFileClassLocator())->classesIn(self::SRC);
        $read = (new ClassFileTypes())->imports(ShowRowResponse::class, $classFiles[ShowRowResponse::class], self::SRC, $classFiles);

        // Still an import, for what only reads its keys; and why nothing sent can be typed with it.
        self::assertSame(RowSource::class, $read['imports']['Row']->targetClass);
        self::assertSame(['Row' => '"Row" is #[PhpStanOnly] on ' . RowSource::class . ': it is never emitted, so nothing sent can be typed with it.'], $read['phpStanOnly']);
        self::assertSame([], $read['unresolved']);
    }

    public function test_a_file_is_read_once_however_often_it_is_asked_for(): void
    {
        $classFiles = (new PhpFileClassLocator())->classesIn(self::SRC);
        $files = new ClassFileTypes();
        $file = $classFiles[RowSource::class];

        self::assertSame(['Row'], array_keys($files->definitions($file)), 'A #[PhpStanOnly] alias is still the file\'s');
        self::assertSame($files->imports(ShowRowResponse::class, $classFiles[ShowRowResponse::class], self::SRC, $classFiles), $files->imports(ShowRowResponse::class, $classFiles[ShowRowResponse::class], self::SRC, $classFiles));
        self::assertNull($files->content(self::SRC . '/missing.php'));
    }
}
