<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\View\NoteView;

/**
 * A paged list that redeclares the page it inherits, by promoting it in its own constructor. The
 * redeclaration carries no attribute of its own, and is still sent as `$pagination`.
 *
 * @phpstan-import-type _self from NoteView as NoteData
 * @phpstan-import-type PageData from NoteView
 */
final class RecentNotesResponse extends PagedNotes implements HttpOk
{
    public function __construct(
        /** @var list<NoteData> */
        public readonly array $notes,
        /** @var PageData */
        public array $pagination,
    ) {}
}
