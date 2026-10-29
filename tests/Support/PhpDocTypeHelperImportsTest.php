<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use RuntimeException;

/**
 * A file's `@phpstan-import-type` tags: an import from outside the source tree is set aside, for
 * whoever reads the file to fail on if it is used.
 */
final class PhpDocTypeHelperImportsTest extends TestCase
{
    private const string SRC = '/src';

    private const array CLASS_FILES = [
        'App\\Billing\\View\\InvoiceView' => '/src/Billing/View/InvoiceView.php',
        'App\\Common\\View\\PageView' => '/src/Common/View/PageView.php',
    ];

    private const string RESOLVED = <<<'PHP'
        <?php
        /**
         * @phpstan-import-type _self from \App\Common\View\PageView as Page
         * @phpstan-import-type Cursor from \App\Common\View\PageView
         */
        final class InvoiceView {}
        PHP;

    private const string ONE_UNRESOLVED = <<<'PHP'
        <?php
        /**
         * @phpstan-import-type _self from \App\Common\View\PageView as Page
         * @phpstan-import-type Amount from \Vendor\Library\Money
         */
        final class InvoiceView {}
        PHP;

    public function test_each_import_is_known_by_its_local_alias(): void
    {
        $read = $this->readLeniently(self::RESOLVED);

        self::assertSame(['Page', 'Cursor'], array_keys($read['imports']));
        self::assertSame('PageView', $read['imports']['Page']->targetTypeName);
        self::assertSame('_self', $read['imports']['Page']->sourceAlias);
        self::assertSame('Common', $read['imports']['Page']->targetDomain);
        self::assertSame('Cursor', $read['imports']['Cursor']->targetTypeName);
        self::assertSame([], $read['unresolved']);
    }

    public function test_read_leniently_it_is_set_aside_by_its_local_alias_with_why(): void
    {
        $read = $this->readLeniently(self::ONE_UNRESOLVED);

        self::assertSame(['Page'], array_keys($read['imports']));
        self::assertSame(['Amount' => 'Class reference "\\Vendor\\Library\\Money" from "App\\Billing\\View\\InvoiceView" could not be resolved.'], $read['unresolved']);
    }

    public function test_the_class_an_import_names_is_the_one_the_files_use_statements_name(): void
    {
        // As PHP and PHPStan resolve it: through `use`, before the file's namespace — a class of the
        // same short name there is not the one meant — and under the alias `use … as` gives it.
        $classFiles = [
            'App\\Notes\\NoteView' => '/src/Notes/NoteView.php',
            'App\\Notes\\Other' => '/src/Notes/Other.php',
            'App\\Common\\Shape\\Other' => '/src/Common/Shape/Other.php',
            'App\\Common\\Shape\\Shapes' => '/src/Common/Shape/Shapes.php',
        ];
        $content = <<<'PHP'
            <?php
            namespace App\Notes;

            use App\Common\Shape\Other;
            use App\Common\Shape\Shapes as CommonShapes;
            use App\Common;

            /**
             * @phpstan-import-type Tag from Other
             * @phpstan-import-type Label from CommonShapes
             * @phpstan-import-type Badge from Common\Shape\Shapes
             */
            final class NoteView {}
            PHP;

        $imports = (new PhpDocTypeHelper())->extractImportedTypes($content, 'App\\Notes\\NoteView', self::SRC, $classFiles, ['Other' => ['App\\Notes\\Other', 'App\\Common\\Shape\\Other'], 'Shapes' => ['App\\Common\\Shape\\Shapes']], new DomainGuesser());

        self::assertSame('App\\Common\\Shape\\Other', $imports['Tag']->targetClass);
        self::assertSame('App\\Common\\Shape\\Shapes', $imports['Label']->targetClass);
        self::assertSame('App\\Common\\Shape\\Shapes', $imports['Badge']->targetClass, 'A namespace `use` names the rest of it');
    }

    public function test_read_strictly_an_import_from_outside_the_tree_fails(): void
    {
        // The reader every release before took: still there, for a caller that wants each import.
        $helper = new PhpDocTypeHelper();
        self::assertSame(['Page', 'Cursor'], array_keys($helper->extractImportedTypes(self::RESOLVED, 'App\\Billing\\View\\InvoiceView', self::SRC, self::CLASS_FILES, [], new DomainGuesser())));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be resolved');
        $helper->extractImportedTypes(self::ONE_UNRESOLVED, 'App\\Billing\\View\\InvoiceView', self::SRC, self::CLASS_FILES, [], new DomainGuesser());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function spellings(): iterable
    {
        yield 'as a type' => ['list<Amount>', true];
        yield 'in a union' => ['Amount|null', true];
        yield 'as a shape key' => ['array{Amount: int}', false];
        yield 'as a string literal' => ["'Amount'|'other'", false];
        yield 'inside a longer name' => ['AmountView', false];
        yield 'as a qualified class name' => ['\\Vendor\\Library\\Amount', false];
    }

    #[DataProvider('spellings')]
    public function test_an_unresolved_import_is_used_only_where_a_type_names_it(string $type, bool $used): void
    {
        $failure = (new PhpDocTypeHelper())->unresolvedImportUsedIn((new PhpDocShapeParser())->parse($type), ['Amount' => 'Amount is not found.'], 'This alias');

        self::assertSame($used, null !== $failure);
    }

    /**
     * @return array{imports: array<string, \PTGS\TypeBridge\Model\ImportedType>, unresolved: array<string, string>}
     */
    private function readLeniently(string $content): array
    {
        $helper = new PhpDocTypeHelper();

        return $helper->importedTypes($helper->importTags($content), 'App\\Billing\\View\\InvoiceView', self::SRC, self::CLASS_FILES, [], new DomainGuesser());
    }
}
