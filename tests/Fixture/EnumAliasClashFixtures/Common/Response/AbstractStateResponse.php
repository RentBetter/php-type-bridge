<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumAliasClashFixtures\Common\Response;

use PTGS\TypeBridge\Tests\Fixture\EnumAliasClashFixtures\Common\Enum\NoteState;

abstract class AbstractStateResponse
{
    /** @var value-of<NoteState> */
    public string $state = 'open';
}
