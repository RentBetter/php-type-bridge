<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConfigAliasModuleFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

abstract class AbstractIdResponse implements HttpOk
{
    /** @var UuidStr */
    public string $id = '';
}
