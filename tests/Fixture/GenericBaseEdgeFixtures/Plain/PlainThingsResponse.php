<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseEdgeFixtures\Plain;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * PHPStan's own keywords, as it reads them: `array` any array, `list` any list, `object` and
 * `iterable` anything.
 */
final class PlainThingsResponse implements ApiResponse, HttpOk
{
    /** @var array */
    public array $data = [];

    /** @var object */
    public object $more;

    /** @var iterable */
    public iterable $each = [];

    /** @var list */
    public array $rows = [];
}
