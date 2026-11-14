<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ScopedEnumFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\ScopedEnumFixtures\Common\BaseValueResponse;

/**
 * Binds the base's param to this module's enum, named bare where Common has an alias of its name.
 *
 * @extends BaseValueResponse<Status>
 */
final class ShowValueResponse extends BaseValueResponse
{
}
