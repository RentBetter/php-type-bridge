<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Minimal HttpKernelInterface so ViewEvent / ExceptionEvent can be constructed in tests
 * without booting a full kernel.
 */
final class StubHttpKernel implements HttpKernelInterface
{
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        return new Response();
    }
}
