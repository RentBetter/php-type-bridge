<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Sorting;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Sorting\AlphabeticalOrder;
use PTGS\TypeBridge\Sorting\DeclaredOrder;

final class SortStrategyTest extends TestCase
{
    public function test_declared_order_preserves_input(): void
    {
        $strategy = new DeclaredOrder();

        self::assertSame(
            ['cherry', 'apple', 'banana'],
            $strategy->sort(['cherry', 'apple', 'banana'], static fn (string $s): string => $s),
        );
    }

    public function test_alphabetical_order_sorts_by_key(): void
    {
        $strategy = new AlphabeticalOrder();

        self::assertSame(
            ['apple', 'banana', 'cherry'],
            $strategy->sort(['cherry', 'apple', 'banana'], static fn (string $s): string => $s),
        );
    }

    public function test_alphabetical_order_keeps_declared_order_when_keys_are_null(): void
    {
        $strategy = new AlphabeticalOrder();

        // A curated, key-less group (e.g. a common module) must not be reordered.
        self::assertSame(
            ['zulu', 'alpha', 'mike'],
            $strategy->sort(['zulu', 'alpha', 'mike'], static fn (string $s): ?string => null),
        );
    }

    public function test_alphabetical_order_sorts_objects_by_extracted_key(): void
    {
        $strategy = new AlphabeticalOrder();

        $beta = (object) ['name' => 'Beta'];
        $alpha = (object) ['name' => 'Alpha'];

        $sorted = $strategy->sort([$beta, $alpha], static fn (object $o): string => $o->name);

        self::assertSame(['Alpha', 'Beta'], array_map(static fn (object $o): string => $o->name, $sorted));
    }
}
