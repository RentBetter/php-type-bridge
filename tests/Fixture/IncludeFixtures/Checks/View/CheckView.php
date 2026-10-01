<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IncludeFixtures\Checks\View;

/**
 * `debug` is always there when it is asked for; `documentation` may not be, even then.
 *
 * @phpstan-type CheckData = array{
 *     name: string,
 *     debug: included<mixed>,
 *     documentation?: included<string>,
 *     lastRun?: array{at: string, readings: included<list<string>>},
 * }
 */
final class CheckView {}
