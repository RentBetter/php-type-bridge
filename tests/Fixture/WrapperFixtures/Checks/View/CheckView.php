<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\WrapperFixtures\Checks\View;

/**
 * Keys a project's own PHPStan extension reads as `T|Optional<T>`: only sent when asked for.
 *
 * @phpstan-type CheckData = array{name: string, debug?: included<mixed>, documentation?: included<string>, readings?: included<list<array{label: string}>>}
 */
final class CheckView {}
