<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractListResponse;

/**
 * Passes its own param on, and binds the labels.
 *
 * @template N
 *
 * @extends AbstractListResponse<N, 'pinned'|'archived'>
 */
abstract class AbstractNoteListResponse extends AbstractListResponse
{
}
