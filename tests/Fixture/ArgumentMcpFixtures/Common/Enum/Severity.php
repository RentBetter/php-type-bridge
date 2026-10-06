<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum;

/**
 * Int-backed, with an id that is its case name rather than its backing value — the shape of
 * property-api's status enums, whose form matches on the id.
 */
enum Severity: int
{
    case OK = 0;
    case WARNING = 1;
    case ERROR = 2;
    case CRITICAL = 3;
    case SKIPPED = -1;

    public function id(): string
    {
        return $this->name;
    }
}
