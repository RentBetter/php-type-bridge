<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Controller;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Query parameters must be bound through a form declared with #[ApiRequest(query: ...)], never
 * read off the Request by hand.
 *
 * `$request->query->getString('role')` is invisible to the contract twice over: the emitted
 * endpoint map advertises no query parameters at all, so a generated client or an MCP tool cannot
 * discover the filter exists; and nothing validates the value, so the usual `Enum::tryFrom($raw)`
 * that follows turns an unrecognised value into null — which callers read as "no filter" and
 * silently return *everything*. A filter that quietly does not apply is worse than one that
 * errors, because the caller reports the result as fact.
 *
 * The fix is a filter form (`AbstractFilterType` with a `data_class`) bound via the request form
 * processor, and `#[ApiRequest(query: MyFilterType::class)]` on the route. The parameters then
 * appear in the generated contract, unknown values are a 422, and the DTO arrives typed.
 *
 * This is the query-side counterpart to forbidding `Request::toArray()`/`getPayload()`/
 * `getContent()` for request bodies.
 *
 * Scoped to controllers: the form processor that performs the binding has to read the bag, and so
 * do listeners and middleware that legitimately work at the HTTP edge. It is a controller reading
 * raw input that bypasses the contract.
 *
 * @implements Rule<MethodCall>
 */
final class NoRequestQueryAccessRule implements Rule
{
    private const string REQUEST_CLASS = 'Symfony\\Component\\HttpFoundation\\Request';

    /** @var list<string> Request properties that are raw input bags. */
    private const array INPUT_BAG_PROPERTIES = ['query', 'request'];

    /**
     * @param list<string> $controllerClassSuffixes class-name suffixes the rule applies to
     */
    public function __construct(
        private readonly array $controllerClassSuffixes = ['Controller'],
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->inController($scope)) {
            return [];
        }

        if (!$node->var instanceof PropertyFetch) {
            return [];
        }

        $fetch = $node->var;
        if (!$fetch->name instanceof Node\Identifier) {
            return [];
        }

        $property = $fetch->name->name;
        if (!\in_array($property, self::INPUT_BAG_PROPERTIES, true)) {
            return [];
        }

        if (!(new ObjectType(self::REQUEST_CLASS))->isSuperTypeOf($scope->getType($fetch->var))->yes()) {
            return [];
        }

        $method = $node->name instanceof Node\Identifier ? $node->name->name : 'get';

        return [
            RuleErrorBuilder::message(\sprintf(
                '$request->%s->%s() reads input straight off the Request. Bind it with a form and declare '
                . '#[ApiRequest(%s: MyFilterType::class)], so the parameter appears in the generated contract '
                . 'and an invalid value is a 422 rather than a silently ignored filter.',
                $property,
                $method,
                'query' === $property ? 'query' : 'body',
            ))
                ->identifier('typeBridge.noRequestQueryAccess')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function inController(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return false;
        }

        // An abstract base controller is where shared binding helpers live (getIncludes(),
        // processQueryForm()); reading the bag is its job. Route handlers are concrete.
        if ($classReflection->isAbstract()) {
            return false;
        }

        foreach ($this->controllerClassSuffixes as $suffix) {
            if (str_ends_with($classReflection->getName(), $suffix)) {
                return true;
            }
        }

        return false;
    }
}
