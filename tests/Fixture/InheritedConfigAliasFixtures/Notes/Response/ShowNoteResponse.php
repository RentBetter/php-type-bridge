<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConfigAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\InheritedConfigAliasFixtures\Common\Response\AbstractIdResponse;

final class ShowNoteResponse extends AbstractIdResponse
{
    /** @var UuidStr */
    public string $owner = '';
}
