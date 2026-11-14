<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

/**
 * Its keys typed with aliases — imported, and its own — read as what each is declared, and those it
 * takes from the shape it extends.
 *
 * @phpstan-import-type _self from Label as LabelShape
 * @phpstan-import-type LabelRemoval from Label
 * @phpstan-type Mode = 'merge'|'replace'
 * @phpstan-type Reason = string|null
 * @phpstan-type _self = LabelRemoval & array{
 *     set?: list<string|LabelShape>,
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
