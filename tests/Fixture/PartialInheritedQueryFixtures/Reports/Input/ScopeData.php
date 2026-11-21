<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PartialInheritedQueryFixtures\Reports\Input;

/**
 * The middle of a chain whose root is imported from outside the source tree, so its keys can't be read.
 *
 * @phpstan-import-type _self from \Vendor\Reports\RootScope as RootScope
 * @phpstan-type _self = RootScope & array{
 *     region?: string,
 * }
 */
final class ScopeData {}
