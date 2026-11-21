<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassAliasNameFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\ClassAliasNameFixtures\Common\Shapes\CommonShapes;

/**
 * Imports Common's `Money` alias and names Billing's Money class in full: each is the type it names.
 *
 * @phpstan-import-type Money from CommonShapes
 */
final class ShowPriceResponse implements HttpOk
{
    /** @var \PTGS\TypeBridge\Tests\Fixture\ClassAliasNameFixtures\Billing\Model\Money */
    public array $price = [];

    /** @var Money */
    public array $total = [];
}
