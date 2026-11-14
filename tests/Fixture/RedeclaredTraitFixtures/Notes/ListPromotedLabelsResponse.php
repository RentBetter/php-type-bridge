<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RedeclaredTraitFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\RedeclaredTraitFixtures\Common\HasLabels;

/**
 * Redeclares a trait's property promoted, with the same doc comment.
 *
 * @phpstan-import-type Tag from NoteTagView
 */
final class ListPromotedLabelsResponse implements ApiResponse, HttpOk
{
    use HasLabels;

    public function __construct(
        /** @var list<Tag> */
        public array $labels,
    ) {}
}
