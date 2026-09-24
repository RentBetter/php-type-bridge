<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OptionalResponseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\OptionalResponseFixtures\Notes\View\NoteView;

/**
 * A resource with a side-load that is only there when asked for (`?T`, off the wire when null),
 * beside one whose null is a real answer (`T|null`, always on the wire).
 *
 * @phpstan-import-type _self from NoteView as NoteData
 */
final class ShowNoteResponse implements HttpOk
{
    public function __construct(
        /** @var NoteData */
        public readonly array $note,
        /** @var ?list<NoteData> */
        public readonly ?array $replies = null,
        /** @var list<NoteData>|null */
        public readonly ?array $history = null,
    ) {}
}
