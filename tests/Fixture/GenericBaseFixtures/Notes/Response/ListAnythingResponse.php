<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractListResponse;

/** Extends it unbound: each param is its bound, or anything. */
final class ListAnythingResponse extends AbstractListResponse implements HttpOk
{
}
