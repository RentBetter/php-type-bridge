<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnClassAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\OwnClassAliasFixtures\Common\Response\AbstractDigestResponse;

/** Inherits a property naming a class with no `_self`: it reads as the module's alias, as an own one would. */
final class ShowDigestResponse extends AbstractDigestResponse
{
}
