<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

final class RankedListResponse implements HttpOk
{
    /**
     * @use HasRows<int>
     * @phpstan-use HasRows<string, string>
     */
    use HasRows;
}
