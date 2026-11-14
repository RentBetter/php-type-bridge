<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * An update body extending a shape: the keys it inherits are what that shape declares — optional,
 * and the description one an update may clear.
 *
 * @phpstan-type ReportFields = array{
 *     title?: string,
 *     description?: string|null,
 * }
 * @phpstan-type _self = ReportFields & array{
 *     pinned?: bool,
 * }
 */
final class ReportFieldsData
{
    public ?string $title = null;
    public ?string $description = null;
    public ?bool $pinned = null;
}
