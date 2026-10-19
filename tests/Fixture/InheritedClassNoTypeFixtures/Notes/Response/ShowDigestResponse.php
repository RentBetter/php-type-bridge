<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedClassNoTypeFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\InheritedClassNoTypeFixtures\Common\Response\AbstractDigestResponse;

/**
 * Inherits a property naming a class with no `_self`, written in the Common module: the Notes
 * module's alias of the same name is not what that file meant, so it is an error to name.
 */
final class ShowDigestResponse extends AbstractDigestResponse
{
}
