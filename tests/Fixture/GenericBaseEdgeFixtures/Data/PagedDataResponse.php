<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Data;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * T bound by nothing is its bound, `array`.
 *
 * @template T of array
 */
abstract class PagedDataResponse implements ApiResponse, HttpOk
{
    /** @var T */
    public array $data = [];
}
