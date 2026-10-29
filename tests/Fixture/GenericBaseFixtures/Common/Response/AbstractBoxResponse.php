<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response;

/**
 * @template T
 */
abstract class AbstractBoxResponse
{
    /** @var T */
    public mixed $item = null;

    /** @var list<T> */
    public array $items = [];
}
