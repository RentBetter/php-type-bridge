<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input;

/**
 * A comment.
 *
 * @phpstan-type _self = array{
 *     body: string,
 *     replies?: list<CommentData>,
 * }
 */
final class CommentData
{
    public ?string $body = null;

    /** @var list<CommentData> */
    public array $replies = [];
}
