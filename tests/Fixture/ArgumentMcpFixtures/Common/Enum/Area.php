<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum;

/**
 * Backed by lowercase strings and identified by its case names, so the values Symfony's EnumType
 * accepts and the ids an id-matching type accepts are different sets.
 */
enum Area: string
{
    case SECURITY = 'security';
    case COST = 'cost';

    public function id(): string
    {
        return $this->name;
    }
}
