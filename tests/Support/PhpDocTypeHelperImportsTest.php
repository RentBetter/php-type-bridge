<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use RuntimeException;

/**
 * A file's `@phpstan-import-type` tags, read strictly — every target must be in the source tree —
 * or leniently, setting an unresolved one aside for whoever reads the file to fail on if it is used.
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

    public function test_each_import_is_known_by_its_local_alias_whichever_way_it_is_read(): void
    {
        $helper = new PhpDocTypeHelper();

        $strict = $helper->extractImportedTypes(self::RESOLVED, 'App\\Billing\\View\\InvoiceView', self::SRC, self::CLASS_FILES, [], new DomainGuesser());
        $lenient = $helper->extractImportedTypesLeniently(self::RESOLVED, 'App\\Billing\\View\\InvoiceView', self::SRC, self::CLASS_FILES, [], new DomainGuesser());

        self::assertSame(['Page', 'Cursor'], array_keys($strict));
        self::assertSame('PageView', $strict['Page']->targetTypeName);
        self::assertSame('_self', $strict['Page']->sourceAlias);
        self::assertSame('Common', $strict['Page']->targetDomain);
        self::assertSame('Cursor', $strict['Cursor']->targetTypeName);
        self::assertEquals($strict, $lenient['imports']);
        self::assertSame([], $lenient['unresolved']);
    }

    public function test_read_strictly_an_import_from_outside_the_source_tree_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Class reference "\\Vendor\\Library\\Money" from "App\\Billing\\View\\InvoiceView" could not be resolved.');

        (new PhpDocTypeHelper())->extractImportedTypes(self::ONE_UNRESOLVED, 'App\\Billing\\View\\InvoiceView', self::SRC, self::CLASS_FILES, [], new DomainGuesser());
    }

    public function test_read_leniently_it_is_set_aside_by_its_local_alias_with_why(): void
    {
        $read = (new PhpDocTypeHelper())->extractImportedTypesLeniently(self::ONE_UNRESOLVED, 'App\\Billing\\View\\InvoiceView', self::SRC, self::CLASS_FILES, [], new DomainGuesser());

        self::assertSame(['Page'], array_keys($read['imports']));
        self::assertSame(['Amount' => 'Class reference "\\Vendor\\Library\\Money" from "App\\Billing\\View\\InvoiceView" could not be resolved.'], $read['unresolved']);
    }
}
