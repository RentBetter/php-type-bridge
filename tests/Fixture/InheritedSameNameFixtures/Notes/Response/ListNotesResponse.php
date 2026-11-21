<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSameNameFixtures\Notes\Response;

use PTGS\TypeBridge\Tests\Fixture\InheritedSameNameFixtures\Common\Response\AbstractListResponse;

/**
 * @phpstan-import-type _self from Meta as NoteMeta
 */
final class ListNotesResponse extends AbstractListResponse
{
    /**
     * @param NoteMeta $meta
     * @param array{page: int} $page
     */
    public function __construct(
        public readonly array $meta,
        array $page,
    ) {
        parent::__construct($page);
    }
}
