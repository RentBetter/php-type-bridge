<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes\HasRows as Rows;

final class AliasedListResponse implements HttpOk
{
    /** @use Rows<string> */
    use Rows;
}
