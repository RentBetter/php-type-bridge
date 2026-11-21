<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RefNamedTypeFixtures\Checks;

/**
 * A type of its own named `Ref`, beside a reference, which emits as the `Ref<T>` helper.
 *
 * @phpstan-type Ref = array{id: string}
 * @phpstan-type AuthorData = array{id: string, name: string}
 * @phpstan-type CheckData = array{author: ref<AuthorData>, at: Ref}
 */
final class CheckView {}
