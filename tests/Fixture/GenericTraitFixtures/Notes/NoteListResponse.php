<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * @use HasRows<string>
 */
final class NoteListResponse implements HttpOk
{
    use HasRows;
}
