<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RedeclaredTraitFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\RedeclaredTraitFixtures\Common\HasTags;

/**
 * Takes the trait's property as it is: a param, and a method's static variable, of its name are no
 * declaration of it.
 *
 * @phpstan-import-type Tag from NoteTagView
 */
final class ListTraitTagsResponse implements ApiResponse, HttpOk
{
    use HasTags;

    public function __construct(array $tags = [])
    {
        $this->tags = $tags;
    }

    public static function counted(): int
    {
        static $tags = 0;

        return ++$tags;
    }
}
