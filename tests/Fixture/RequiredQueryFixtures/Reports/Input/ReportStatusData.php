<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * An update whose status may be cleared, chosen with radio buttons: an expanded choice reads what
 * is sent itself, null included.
 *
 * @phpstan-type _self = array{
 *     status?: 'open'|'closed'|null,
 * }
 */
final class ReportStatusData
{
    public ?string $status = null;
}
