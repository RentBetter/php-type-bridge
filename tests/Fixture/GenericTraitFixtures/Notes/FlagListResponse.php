<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * @extends RowsBase<bool>
 */
final class FlagListResponse extends RowsBase implements HttpOk
{
}
