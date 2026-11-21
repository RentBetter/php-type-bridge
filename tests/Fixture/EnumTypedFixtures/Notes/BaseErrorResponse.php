<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumTypedFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** A base whose property is typed natively with an enum. */
abstract class BaseErrorResponse implements ApiResponse, HttpOk
{
    public ErrorCode $code = ErrorCode::Conflict;
}
