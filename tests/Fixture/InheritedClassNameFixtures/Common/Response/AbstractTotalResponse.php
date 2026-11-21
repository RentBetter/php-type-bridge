<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedClassNameFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/** Names a class, not an alias: its `total` is the JSON Money serialises to. */
abstract class AbstractTotalResponse implements HttpOk
{
    public function __construct(
        /** @var Money */
        public readonly array $total,
    ) {}
}
