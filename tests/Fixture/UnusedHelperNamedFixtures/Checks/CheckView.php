<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnusedHelperNamedFixtures\Checks;

/**
 * A reference, and a key sent only when asked for: this module declares both helpers.
 *
 * @phpstan-type AuthorData = array{id: string, name: string}
 * @phpstan-type CheckData = array{author: ref<AuthorData>, debug: included<string>}
 */
final class CheckView {}
