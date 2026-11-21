<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ListItemNamedTypeFixtures\Checks;

/**
 * A type of its own named `ListItem`, as one the expanding helpers declare, beside a reference.
 *
 * @phpstan-type ListItem = array{label: string}
 * @phpstan-type AuthorData = array{id: string, name: string}
 * @phpstan-type CheckData = array{author: ref<AuthorData>, items: list<ListItem>}
 */
final class CheckView {}
