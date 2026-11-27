<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Attribute\Metadata;
use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\View\NoteView;

/**
 * A page of a list, and what the response says of the page: metadata, sent as `$pagination`.
 *
 * @phpstan-import-type _self from NoteView as NoteData
 * @phpstan-import-type PageData from NoteView
 */
final class ListNotesResponse implements HttpOk
{
    public function __construct(
        /** @var list<NoteData> */
        public readonly array $notes,
        /** @var PageData */
        #[Metadata]
        public readonly array $pagination,
    ) {}
}
