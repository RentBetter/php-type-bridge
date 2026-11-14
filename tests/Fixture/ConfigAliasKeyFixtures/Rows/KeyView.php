<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ConfigAliasKeyFixtures\Rows;

/**
 * Maps keyed by project-wide aliases: of any key, and of a string.
 *
 * @phpstan-type Keyed = array{byAny: array<AnyKey, string>, byText: array<Text, string>}
 */
final class KeyView {}
