<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Inherits a key typed with an alias that admits null, read where the shape it extends is declared:
 * a query a caller may leave out.
 *
 * @phpstan-type MaybeTeam = string|null
 * @phpstan-type AliasedScope = array{
 *     team: MaybeTeam,
 * }
 * @phpstan-type _self = AliasedScope & array{
 *     page?: int,
 * }
 */
final class AliasedScopeFilterData
{
    public ?string $team = null;
    public ?int $page = null;
}
