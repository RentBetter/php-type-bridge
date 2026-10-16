<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedAliasClashFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpConflict;
use PTGS\TypeBridge\Tests\Fixture\InheritedAliasClashFixtures\Common\Response\AbstractErrorResponse;

/**
 * Imports another shape under the alias its base class uses.
 *
 * @phpstan-import-type _self from NoteBody as Body
 */
final class NoteRejectedResponse extends AbstractErrorResponse implements HttpConflict
{
    /** @var Body */
    public array $note;

    /**
     * @param Body $note
     */
    public function __construct(array $note)
    {
        $this->note = $note;

        parent::__construct('REJECTED');
    }
}
