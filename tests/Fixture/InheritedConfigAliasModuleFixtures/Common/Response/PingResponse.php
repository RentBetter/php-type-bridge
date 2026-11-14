<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConfigAliasModuleFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * The base's module emits something of its own, which never mentions the config alias: so it
 * declares none for another module to import.
 */
final class PingResponse implements HttpOk
{
    public bool $ok = true;
}
