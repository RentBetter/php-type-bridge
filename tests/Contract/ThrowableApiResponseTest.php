<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Contract;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Tests\Http\Fixtures\NotFoundError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ThrowableApiResponseTest extends TestCase
{
    public function test_a_thrown_response_is_an_http_exception_carrying_its_marker_status(): void
    {
        // Symfony's ErrorListener logs anything that is not an HttpExceptionInterface as a
        // critical uncaught exception. A thrown 404 is a client error and must read as one.
        $error = new NotFoundError(resource: 'project');

        self::assertInstanceOf(HttpExceptionInterface::class, $error);
        self::assertSame(404, $error->getStatusCode());
        self::assertSame([], $error->getHeaders());
    }
}
