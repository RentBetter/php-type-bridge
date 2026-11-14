<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CrossModuleEnumFixtures\Common\Response;

use PTGS\TypeBridge\Tests\Fixture\CrossModuleEnumFixtures\Common\Enum\NoteState;

/**
 * Abstract, so emits nothing of its own; nothing else in Common names NoteState. Its property is
 * published only where a response in another module inherits it.
 */
abstract class AbstractStateResponse
{
    /** @var value-of<NoteState> */
    public string $state = 'open';
}
