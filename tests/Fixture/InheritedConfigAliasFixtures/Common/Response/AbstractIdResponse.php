<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConfigAliasFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * A module holding only a base: it emits nothing of its own, so a config alias its property is
 * typed with is declared nowhere but where the property is emitted.
 */
abstract class AbstractIdResponse implements HttpOk
{
    /** @var UuidStr */
    public string $id = '';
}
