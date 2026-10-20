<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Common\Response\AbstractEnvelope;
use PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Common\Response\NoteSummary;

final class ShowNoteResponse extends AbstractEnvelope implements HttpOk
{
    public string $id = '';

    public ?int $rank = null;

    public ?NoteSummary $summary = null;
}
