<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSameNameFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * @phpstan-import-type _self from Meta as PageMeta
 */
abstract class AbstractListResponse implements HttpOk
{
    public function __construct(
        /** @var PageMeta */
        public readonly array $page,
    ) {}
}
