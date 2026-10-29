<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Model\RowSource;
use PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Response\ShowRowResponse;
use PTGS\TypeBridge\Tests\Fixture\UnloadableFixtures\DevOnly\DevOnlyRule;
use PTGS\TypeBridge\Tests\Fixture\UnloadableImportFixtures\DevOnly\DevOnlyCodes;
use PTGS\TypeBridge\Tests\Fixture\UnloadableImportFixtures\Notes\Input\NoteData;
use PTGS\TypeBridge\Tests\Fixture\UnloadableSelfImportFixtures\DevOnly\DevOnlyNote;
use PTGS\TypeBridge\Tests\Fixture\UnloadableSelfImportFixtures\Notes\NoteData as SelfImportingNoteData;

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

    public function test_an_import_from_a_class_this_install_cannot_load_is_set_aside_with_why(): void
    {
        // Its declaration throws without a require-dev package: it is no part of the application, so
        // nothing it declares is emitted, and asking about it must not take generation down.
        $src = __DIR__ . '/../Fixture/UnloadableImportFixtures';
        $classFiles = (new PhpFileClassLocator())->classesIn($src);
        $read = (new ClassFileTypes())->imports(NoteData::class, $classFiles[NoteData::class], $src, $classFiles);

        self::assertArrayNotHasKey('Code', $read['imports']);
        self::assertStringContainsString(DevOnlyCodes::class . ' cannot be loaded', $read['unresolved']['Code'] ?? '');
        self::assertNull(PhpStanOnly::of(DevOnlyRule::class));
    }

    public function test_a_self_imported_from_a_class_this_install_cannot_load_is_set_aside_with_why(): void
    {
        // Naming its `_self` asks whether it is an enum, which loads it: that must not throw either.
        $src = __DIR__ . '/../Fixture/UnloadableSelfImportFixtures';
        $classFiles = (new PhpFileClassLocator())->classesIn($src);
        $read = (new ClassFileTypes())->imports(SelfImportingNoteData::class, $classFiles[SelfImportingNoteData::class], $src, $classFiles);

        self::assertArrayNotHasKey('DevNote', $read['imports']);
        self::assertStringContainsString(DevOnlyNote::class . ' cannot be loaded', $read['unresolved']['DevNote'] ?? '');
    }

    public function test_it_lists_the_classes_a_source_tree_declares(): void
    {
        // As the locator scans them; the collectors sharing one reader ask it, not the locator.
        self::assertSame((new PhpFileClassLocator())->classesIn(self::SRC), (new ClassFileTypes())->classesIn(self::SRC));
    }

    public function test_a_file_is_read_once_however_often_it_is_asked_for(): void
    {
        $classFiles = (new PhpFileClassLocator())->classesIn(self::SRC);
        $files = new ClassFileTypes();
        $file = $classFiles[RowSource::class];

        self::assertSame(['Row'], array_keys($files->definitions($file)), 'A #[PhpStanOnly] alias is still the file\'s');
        self::assertSame($files->imports(ShowRowResponse::class, $classFiles[ShowRowResponse::class], self::SRC, $classFiles), $files->imports(ShowRowResponse::class, $classFiles[ShowRowResponse::class], self::SRC, $classFiles));
        self::assertFalse($files->readable(self::SRC . '/missing.php'));
        self::assertTrue($files->readable($file));
    }
}
