<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyFixtures\Keys;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * A base in another module, its maps keyed by aliases of that module: its own, and another class's,
 * which the module's files name bare.
 *
 * @phpstan-type BaseKey = mixed
 */
abstract class KeyedBaseResponse implements ApiResponse, HttpOk
{
    /** @var array<BaseKey, string> */
    public array $byBase = [];

    /** @var array<Loose, string> */
    public array $byModule = [];
}
