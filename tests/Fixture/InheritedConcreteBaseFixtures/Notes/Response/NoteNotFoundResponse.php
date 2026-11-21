<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConcreteBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\InheritedConcreteBaseFixtures\Common\Response\NotFoundResponse;

/** Read after its base, which is read as a response of its own first. */
final class NoteNotFoundResponse extends NotFoundResponse
{
}
