<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

final class AddProjectNotesRequestData
{
    /** @var list<ProjectNoteData> */
    public array $notes = [];

    /** @var list<string> */
    public array $labels = [];
}
