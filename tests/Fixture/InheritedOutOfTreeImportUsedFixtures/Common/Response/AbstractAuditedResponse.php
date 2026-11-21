<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedOutOfTreeImportUsedFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * Publishes a property typed with a shape from outside the source tree.
 *
 * @phpstan-import-type _self from \PTGS\TypeBridge\Tests\Fixture\OptionalResponseFixtures\Notes\View\NoteView as Audit
 */
abstract class AbstractAuditedResponse implements HttpOk
{
    public function __construct(
        /** @var Audit */
        public readonly array $audit,
    ) {}
}
