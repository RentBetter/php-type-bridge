<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IntEnumTypedFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** A base whose property is typed natively with an int-backed enum. */
abstract class BaseTaskResponse implements ApiResponse, HttpOk
{
    public Priority $priority = Priority::Low;
}
