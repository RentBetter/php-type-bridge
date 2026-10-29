<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response;

/**
 * Params as PHPStan ranks their spellings: `@phpstan-` over `@psalm-` over plain, per name; a
 * default where nothing binds one.
 *
 * @template R
 * @phpstan-template R of int
 * @psalm-template P of string
 * @template D = bool
 */
abstract class AbstractRankedResponse
{
    /** @var list<R> */
    public array $ranks = [];

    /** @var list<P> */
    public array $tags = [];

    /** @var D */
    public mixed $flag = null;
}
