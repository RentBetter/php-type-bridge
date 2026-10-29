<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TraitConstantFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;

final class ShowNoteResponse implements HttpOk
{
    use HasStatus;

    public const string STATUS_OPEN = 'open';
    public const string STATUS_CLOSED = 'closed';

    public function __construct()
    {
        $this->status = self::STATUS_OPEN;
    }
}
