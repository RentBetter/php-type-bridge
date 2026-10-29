<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response\AbstractPairResponse;

/**
 * Bound in PHPStan's spelling, which wins over the plain one beside it, as PHPStan reads it.
 *
 * @extends AbstractPairResponse<int, int>
 * @phpstan-extends AbstractPairResponse<string, bool>
 */
final class NotePairResponse extends AbstractPairResponse implements HttpOk
{
}
