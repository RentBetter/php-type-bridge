<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnusedHelperNamedFixtures\Orders;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\UnusedHelperNamedFixtures\Checks\CheckView;

/**
 * A module whose one reference, and one key sent only when asked for, are a response's.
 *
 * @phpstan-import-type AuthorData from CheckView
 */
final class ShowOrderResponse implements ApiResponse, HttpOk
{
    /** @var ref<AuthorData> */
    public string $author = '';

    /** @var included<string> */
    public string $debug = '';
}
