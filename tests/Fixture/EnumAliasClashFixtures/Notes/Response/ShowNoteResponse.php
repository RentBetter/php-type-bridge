<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumAliasClashFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\EnumAliasClashFixtures\Common\Response\AbstractStateResponse;

/**
 * Its module declares a `NoteState` of its own, beside the Common enum its base names.
 *
 * @phpstan-type NoteState = array{label: string}
 */
final class ShowNoteResponse extends AbstractStateResponse implements HttpOk
{
    /** @var NoteState */
    public array $label = ['label' => ''];
}
