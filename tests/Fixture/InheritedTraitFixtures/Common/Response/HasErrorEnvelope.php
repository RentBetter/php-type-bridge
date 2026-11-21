<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedTraitFixtures\Common\Response;

/**
 * @phpstan-import-type _self from ErrorBody as ErrorBodyData
 */
trait HasErrorEnvelope
{
    /** @var ErrorBodyData */
    public array $error;
}
