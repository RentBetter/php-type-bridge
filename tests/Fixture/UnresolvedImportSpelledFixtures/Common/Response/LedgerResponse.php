<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnresolvedImportSpelledFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * @phpstan-import-type _self from \Vendor\Missing\Money as Money
 */
final class LedgerResponse implements HttpOk
{
    /** @var array{Money: int, label: 'Money'} */
    public array $totals = ['Money' => 0, 'label' => 'Money'];
}
