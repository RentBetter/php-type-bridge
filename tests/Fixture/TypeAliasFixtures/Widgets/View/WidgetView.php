<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TypeAliasFixtures\Widgets\View;

/**
 * References `UuidStr` without importing it — the alias is declared in config, not on a class.
 *
 * @phpstan-type _self = array{
 *     id: UuidStr,
 *     name: string,
 * }
 */
final class WidgetView
{
}
