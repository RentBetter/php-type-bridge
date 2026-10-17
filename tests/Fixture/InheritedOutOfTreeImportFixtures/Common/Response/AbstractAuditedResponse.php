<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedOutOfTreeImportFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * Types a protected property with a shape from outside the source tree; only `total` is public.
 *
 * @phpstan-import-type _self from \PTGS\TypeBridge\Tests\Fixture\OptionalResponseFixtures\Notes\View\NoteView as Audit
 */
abstract class AbstractAuditedResponse implements HttpOk
{
    /** @var Audit|null */
    protected ?array $audit = null;

    public function __construct(
        public readonly int $total,
    ) {}
}
