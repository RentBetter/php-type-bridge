<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedConcreteBaseReadLastFixtures\Common\Response;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;
use PTGS\TypeBridge\Status\HttpNotFound;

/**
 * A response of its own, and the base of others: not final.
 *
 * @phpstan-type Envelope = array{
 *     code: string,
 * }
 */
class NotFoundResponse extends ThrowableApiResponse implements HttpNotFound
{
    /** @var Envelope */
    public array $error;

    public function __construct(string $code)
    {
        $this->error = ['code' => $code];

        parent::__construct($code);
    }
}
