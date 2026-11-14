<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractListResponse;

/**
 * Binds the base's param to its own module's NoteSummary, declared in another of its files.
 *
 * @extends AbstractListResponse<NoteSummary>
 */
final class ListSummariesResponse extends AbstractListResponse implements HttpOk
{
}
