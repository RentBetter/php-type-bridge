<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;

/** A page of a list: how many there are in all, whatever the page holds. */
abstract class AbstractPageResponse implements HttpOk
{
    public function __construct(
        /** @var int<0, max> every one that matches, not only this page */
        public readonly int $total,
    ) {}
}
