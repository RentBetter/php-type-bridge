<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedAliasClashFixtures\Common\Response;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;

/**
 * @phpstan-import-type _self from ErrorBody as Body
 */
abstract class AbstractErrorResponse extends ThrowableApiResponse
{
    /** @var Body */
    public array $error;

    public function __construct(string $code)
    {
        $this->error = ['code' => $code];

        parent::__construct($code);
    }
}
