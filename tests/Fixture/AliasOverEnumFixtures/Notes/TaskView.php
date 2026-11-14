<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AliasOverEnumFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\AliasOverEnumFixtures\Common\Status;

/**
 * `Status` used bare: the module's alias wins over the class the `use` names.
 *
 * @phpstan-type Task = array{status: Status}
 */
final class TaskView {}
