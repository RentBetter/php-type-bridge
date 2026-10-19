<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;

/**
 * The envelope every error carries, declared once on the base class its responses extend.
 *
 * @phpstan-import-type _self from ErrorBody as ErrorBodyData
 */
abstract class AbstractErrorResponse extends ThrowableApiResponse
{
    /** @var ErrorBodyData */
    public array $error;

    public function __construct(string $code, string $message)
    {
        $this->error = ['code' => $code, 'message' => $message];

        parent::__construct($message);
    }
}
