<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConfigAliasModuleFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\InheritedConfigAliasModuleFixtures\Common\Response\AbstractIdResponse;

final class ShowNoteResponse extends AbstractIdResponse
{
    /** @var UuidStr */
    public string $owner = '';
}
