<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative;

use Symfony\Component\HttpFoundation\Request;

/**
 * Query parameters read straight off the Request: invisible to the contract, and unvalidated.
 */
final class RawRequestQueryController
{
    public function listAction(Request $request): array
    {
        $role = $request->query->getString('role');
        $page = $request->query->getInt('page');

        return [$role, $page];
    }

    public function bodyAction(Request $request): mixed
    {
        return $request->request->get('name');
    }

    public function headersAreFine(Request $request): ?string
    {
        // OK - headers are not user-supplied contract input in the same sense, and are not
        // part of any generated endpoint shape.
        return $request->headers->get('X-Trace-Id');
    }
}

/**
 * A base controller providing the shared binding helpers — reading the bag is its job, so it is
 * exempt. Route handlers are concrete.
 */
abstract class BaseQueryController
{
    protected function includes(Request $request): mixed
    {
        // OK - abstract base controllers own the binding helpers
        return $request->query->get('include');
    }
}
