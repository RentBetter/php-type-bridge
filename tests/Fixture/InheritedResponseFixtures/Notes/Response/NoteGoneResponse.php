<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response\AbstractErrorResponse;
use PTGS\TypeBridge\Status\HttpGone;

/** In another domain than the base class and the shape its property names. */
final class NoteGoneResponse extends AbstractErrorResponse implements HttpGone
{
    public function __construct(string $message)
    {
        parent::__construct('GONE', $message);
    }
}
