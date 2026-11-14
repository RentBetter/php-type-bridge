<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Inherits one key from two levels down, declared at both.
 *
 * @phpstan-type DeepScope = array{
 *     region: string|null,
 * }
 * @phpstan-type MidScope = DeepScope & array{
 *     region: string|null,
 * }
 * @phpstan-type _self = MidScope & array{
 *     page?: int,
 * }
 */
final class LayeredRegionFilterData
{
    public ?string $region = null;
    public ?int $page = null;
}
