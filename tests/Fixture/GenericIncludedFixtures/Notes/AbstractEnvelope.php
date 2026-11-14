<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericIncludedFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * @template T
 */
abstract class AbstractEnvelope implements ApiResponse, HttpOk
{
    /** @var T */
    public mixed $data = null;
}
