<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractRankedResponse;

/** Extends it unbound. */
final class RankedNotesResponse extends AbstractRankedResponse implements HttpOk
{
}
