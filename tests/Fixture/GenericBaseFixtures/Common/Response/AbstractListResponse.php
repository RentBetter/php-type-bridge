<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response;

/**
 * A list of what a response that extends it says: `@extends AbstractListResponse<NoteView>`.
 *
 * @template T
 * @template L of string
 */
abstract class AbstractListResponse
{
    /** @var list<T> */
    public array $items = [];

    /** @var T|null */
    public mixed $first = null;

    /** @var list<L> */
    public array $labels = [];
}
