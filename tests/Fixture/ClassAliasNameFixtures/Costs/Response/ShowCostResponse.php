<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassAliasNameFixtures\Costs\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\ClassAliasNameFixtures\Common\Shapes\CommonShapes;

/**
 * Imports Common's `Money` for none of its properties, and names Billing's Money class in full.
 *
 * @phpstan-import-type Money from CommonShapes
 */
final class ShowCostResponse implements HttpOk
{
    /** @var \PTGS\TypeBridge\Tests\Fixture\ClassAliasNameFixtures\Billing\Model\Money */
    public array $cost = [];
}
