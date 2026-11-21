<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedLocalAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpGone;
use PTGS\TypeBridge\Tests\Fixture\InheritedLocalAliasFixtures\Common\Response\AbstractEnvelopeResponse;

final class NoteGoneResponse extends AbstractEnvelopeResponse implements HttpGone
{
    public function __construct(string $message)
    {
        parent::__construct('GONE', $message);
    }
}
