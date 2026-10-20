<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

/**
 * Its keys typed with aliases — imported, and its own — read as what each is declared.
 *
 * @phpstan-import-type _self from Label as LabelShape
 * @phpstan-type Mode = 'merge'|'replace'
 * @phpstan-type Reason = string|null
 * @phpstan-type _self = array{
 *     set?: list<string|LabelShape>,
 *     remove?: list<string>,
 *     mode?: Mode,
 *     reason?: Reason,
 * }
 */
final class LabelOperations
{
    /** @var list<string> */
    public array $set = [];

    /** @var list<string> */
    public array $remove = [];

    public ?string $mode = null;

    public ?string $reason = null;
}
