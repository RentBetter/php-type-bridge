<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include\Fixtures;

use PTGS\TypeBridge\Http\Include\ExpandGuard;

/**
 * Lets through the entities it is given and refuses the rest. Records every entity it is asked
 * about.
 */
final class RecordingExpandGuard implements ExpandGuard
{
    /** @var list<class-string> */
    public array $asked = [];

    /**
     * @param list<class-string> $allowed
     */
    public function __construct(
        private readonly array $allowed = [],
    ) {}

    public function mayExpand(string $entityClass): bool
    {
        $this->asked[] = $entityClass;

        return \in_array($entityClass, $this->allowed, true);
    }
}
