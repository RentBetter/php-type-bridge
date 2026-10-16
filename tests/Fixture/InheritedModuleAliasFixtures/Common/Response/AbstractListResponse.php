<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedModuleAliasFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/** Names an alias of its module without importing it, as a module's own files may. */
abstract class AbstractListResponse implements HttpOk
{
    public function __construct(
        /** @var Page */
        public readonly array $page,
    ) {}
}
