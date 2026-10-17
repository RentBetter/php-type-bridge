<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Tests\Fixture\Enum\Colour;
use PTGS\TypeBridge\Tests\Fixture\ValueOfNameFixtures\Billing\View\Invoice;
use PTGS\TypeBridge\Tests\Fixture\ValueOfNameFixtures\Money\Enum\Currency;

/**
 * The name a type alias is emitted under — one rule, which an import of the alias, an endpoint's
 * input reference and the alias's own declaration all agree on.
 */
final class PhpDocTypeHelperEmittedNameTest extends TestCase
{
    public function test_a_named_alias_is_its_own_name_and_a_classs_self_is_the_class(): void
    {
        $helper = new PhpDocTypeHelper();

        self::assertSame('Page', $helper->emittedTypeName('Page', Invoice::class));
        self::assertSame('Invoice', $helper->emittedTypeName('_self', Invoice::class));
    }

    public function test_an_enums_self_is_its_data_unless_it_names_its_values(): void
    {
        $helper = new PhpDocTypeHelper();

        self::assertSame('ColourData', $helper->emittedTypeName('_self', Colour::class));
        // #[ValueOfName] gives the value union its own name, and the shape the enum's.
        self::assertSame('Currency', $helper->emittedTypeName('_self', Currency::class));
    }
}
