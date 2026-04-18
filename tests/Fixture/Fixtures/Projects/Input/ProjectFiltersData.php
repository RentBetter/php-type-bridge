<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input;

/**
 * @phpstan-type _self = array{
 *     ?search: string,
 *     ?page: int,
 *     ?archived: bool,
 * }
 */
final class ProjectFiltersData
{
    public ?string $search = null;
    public ?int $page = null;
    public ?bool $archived = null;
}
