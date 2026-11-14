<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractListResponse;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\CommonShapes;

/**
 * Binds the base's param to a type its file imports from another module.
 *
 * @phpstan-import-type Tag from CommonShapes
 *
 * @extends AbstractListResponse<Tag>
 */
final class ListTaggedNotesResponse extends AbstractListResponse implements HttpOk
{
}
