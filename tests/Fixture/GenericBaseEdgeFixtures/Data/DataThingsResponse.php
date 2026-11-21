<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Data;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * Binds no param: PHPStan below level 6 allows it.
 *
 * @phpstan-ignore missingType.generics
 */
final class DataThingsResponse extends PagedDataResponse
{
}
