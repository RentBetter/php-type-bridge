<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GlobalNamedFixtures\Notes;

/**
 * A type named as TypeScript's global `Record`, in a module whose code writes a map with it.
 *
 * @phpstan-type _self = array{id: string}
 */
final class Record {}
