<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RedeclaredTraitFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\RedeclaredTraitFixtures\Common\HasTags;

/**
 * Redeclares the trait's property, as PHP allows where the two agree, with the same doc comment:
 * written here, it names this file's `Tag`.
 *
 * @phpstan-import-type Tag from NoteTagView
 */
final class ListTagsResponse implements ApiResponse, HttpOk
{
    use HasTags;

    /** @var list<Tag> */
    public array $tags = [];
}
