<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpNotFound;

/** Declares nothing of its own: its body is all the base class's. */
final class NotFoundResponse extends AbstractErrorResponse implements HttpNotFound
{
    public function __construct(string $message)
    {
        parent::__construct('NOT_FOUND', $message);
    }
}
