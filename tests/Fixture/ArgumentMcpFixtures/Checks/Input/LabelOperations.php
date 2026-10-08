<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

/**
 * @phpstan-type _self = array{
 *     set?: list<string|array{name: string, colour?: string}>,
 *     remove?: list<string>,
 *     mode?: 'merge'|'replace',
 * }
 */
final class LabelOperations
{
    /** @var list<string> */
    public array $set = [];

    /** @var list<string> */
    public array $remove = [];

    public ?string $mode = null;
}
