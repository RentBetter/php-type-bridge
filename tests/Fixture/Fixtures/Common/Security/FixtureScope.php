<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security;

enum FixtureScope: string
{
    case PROJECTS_READ = 'projects:read';
    case PROJECTS_WRITE = 'projects:write';
}
