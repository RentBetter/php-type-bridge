<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Model;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Model\ImportedType;

/**
 * The name an import is declared under where it comes from: the one it names, or, where it names
 * none, the name it is imported as — `@phpstan-import-type Foo from Bar` imports Bar's `Foo` as `Foo`.
 */
final class ImportedTypeTest extends TestCase
{
    public function testItIsTheNameItIsImportedAsWhereItNamesNoOther(): void
    {
        self::assertSame('Foo', (new ImportedType('Foo', 'App\\Bar', 'Foo', 'app'))->sourceName());
        self::assertSame('_self', (new ImportedType('BarShape', 'App\\Bar', 'Bar', 'app', sourceAlias: '_self'))->sourceName());
    }
}
