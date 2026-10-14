<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedTraitFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\InheritedTraitFixtures\Common\Response\HasErrorEnvelope;
use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpGone;

final class NoteGoneResponse extends ThrowableApiResponse implements HttpGone
{
    use HasErrorEnvelope;

    public function __construct(string $code)
    {
        $this->error = ['code' => $code];

        parent::__construct($code);
    }
}
