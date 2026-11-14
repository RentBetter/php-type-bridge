<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpConflict;

/**
 * Redeclares the envelope, as an app had to before inherited properties were published: it is
 * still one property.
 *
 * @phpstan-import-type _self from ErrorBody as ErrorBodyData
 */
final class ConflictResponse extends AbstractErrorResponse implements HttpConflict
{
    /** @var ErrorBodyData */
    public array $error;

    public function __construct(string $message)
    {
        parent::__construct('CONFLICT', $message);
    }
}
