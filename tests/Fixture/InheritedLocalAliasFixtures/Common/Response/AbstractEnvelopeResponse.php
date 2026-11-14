<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedLocalAliasFixtures\Common\Response;

use PTGS\TypeBridge\Contract\ThrowableApiResponse;

/**
 * Its property is typed with an alias it declares itself, not one it imports.
 *
 * @phpstan-type Envelope = array{
 *     code: string,
 *     message: string,
 * }
 */
abstract class AbstractEnvelopeResponse extends ThrowableApiResponse
{
    /** @var Envelope */
    public array $error;

    public function __construct(string $code, string $message)
    {
        $this->error = ['code' => $code, 'message' => $message];

        parent::__construct($message);
    }
}
