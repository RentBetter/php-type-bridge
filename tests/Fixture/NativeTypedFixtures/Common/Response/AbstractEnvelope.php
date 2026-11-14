<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NativeTypedFixtures\Common\Response;

/**
 * A base whose properties have only their native types: each is sent as that type says.
 */
abstract class AbstractEnvelope
{
    public array $meta = [];

    public ?string $note = null;

    public int|array $count = 0;
}
