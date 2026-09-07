<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IdOfFixtures\Widgets\View;

/**
 * Exercises the positions an `id-of` can occupy: inside a list, on an optional field, named
 * by a fully qualified class, and twice over for the same enum.
 *
 * @phpstan-type _self = array{
 *     statuses: list<id-of<AlphaStatus>>,
 *     primary: id-of<AlphaStatus>,
 *     stage?: id-of<\PTGS\TypeBridge\Tests\Fixture\Discovered\BetaStatus>,
 * }
 */
final class WidgetListView
{
}
