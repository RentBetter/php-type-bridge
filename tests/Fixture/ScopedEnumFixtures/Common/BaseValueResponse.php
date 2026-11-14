<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ScopedEnumFixtures\Common;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * @template T
 */
abstract class BaseValueResponse implements ApiResponse, HttpOk
{
    /** @var T */
    public mixed $value = null;
}
