<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Notes\Response;

final class ListNotesResponse extends AbstractPageResponse
{
    /**
     * @param list<string> $notes
     */
    public function __construct(
        public readonly array $notes,
        int $total,
    ) {
        parent::__construct($total);
    }
}
