<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Metas;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * @phpstan-type Meta = array{page: int}
 *
 * @extends PagedMetaResponse<Meta>
 */
final class MetaThingsResponse extends PagedMetaResponse
{
}
