<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Attribute\Metadata;
use PTGS\TypeBridge\Tests\Fixture\MetadataResponseFixtures\Notes\View\NoteView;

/**
 * What every paged list of notes says of its page: metadata, sent as `$pagination`.
 *
 * @phpstan-import-type PageData from NoteView
 */
abstract class PagedNotes
{
    /** @var PageData */
    #[Metadata]
    public array $pagination = ['offset' => 0, 'limit' => 0, 'hasMore' => false];
}
