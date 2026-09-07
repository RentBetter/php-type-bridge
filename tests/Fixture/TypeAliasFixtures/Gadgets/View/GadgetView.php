<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TypeAliasFixtures\Gadgets\View;

/**
 * Mentions no alias, so the alias declaration must not appear in this domain.
 *
 * @phpstan-type _self = array{
 *     label: string,
 * }
 */
final class GadgetView
{
}
