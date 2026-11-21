<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryTraitFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\LibraryTraitLib\HasMeta;

final class ShowNoteResponse implements HttpOk
{
    use HasMeta;

    public function __construct(
        public readonly string $id,
    ) {}
}
