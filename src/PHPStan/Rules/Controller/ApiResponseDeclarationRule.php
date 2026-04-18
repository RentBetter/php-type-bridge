<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Controller;

use PhpParser\Node;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Contract\ApiErrorResponse;
use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\PHPStan\Support\ApiMethodInspector;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * @implements Rule<ClassMethod>
 */
final class ApiResponseDeclarationRule implements Rule
{
    private NodeFinder $nodeFinder;

    public function __construct(
        private readonly ApiMethodInspector $apiMethodInspector = new ApiMethodInspector(),
    ) {
        $this->nodeFinder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection || null === $node->name) {
            return [];
        }

        $className = $classReflection->getName();
        $methodName = $node->name->toString();
        $method = $this->apiMethodInspector->inspect($className, $methodName);
        if (null === $method || !$method->isApiRoute() || !$method->hasApiResponses) {
            return [];
        }

        $declared = array_fill_keys($method->declaredResponses, true);
        $errors = [];

        foreach ($this->returnedResponseClasses($className, $methodName) as $responseClass) {
            if (isset($declared[$responseClass])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'API controller method "%s::%s" returns "%s" but it is missing from #[ApiResponses].',
                $className,
                $methodName,
                $responseClass,
            ))->line($node->getStartLine())->build();
        }

        foreach ($this->thrownResponseClasses($node, $scope) as $responseClass) {
            if (isset($declared[$responseClass])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'API controller method "%s::%s" throws "%s" but it is missing from #[ApiResponses].',
                $className,
                $methodName,
                $responseClass,
            ))->line($node->getStartLine())->build();
        }

        return $errors;
    }

    /**
     * @return list<class-string<ApiResponse>>
     */
    private function returnedResponseClasses(string $className, string $methodName): array
    {
        $reflection = new ReflectionMethod($className, $methodName);
        $returnType = $reflection->getReturnType();
        if (null === $returnType) {
            return [];
        }

        $types = [];
        if ($returnType instanceof ReflectionNamedType) {
            $types[] = $returnType;
        } elseif ($returnType instanceof ReflectionUnionType) {
            $types = array_values(array_filter(
                $returnType->getTypes(),
                static fn (\ReflectionType $type): bool => $type instanceof ReflectionNamedType,
            ));
        }

        $responses = [];
        foreach ($types as $type) {
            if ($type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if (is_a($typeName, ApiResponse::class, true)) {
                $responses[] = $typeName;
            }
        }

        return array_values(array_unique($responses));
    }

    /**
     * @return list<class-string<ApiErrorResponse>>
     */
    private function thrownResponseClasses(ClassMethod $node, Scope $scope): array
    {
        if (null === $node->stmts) {
            return [];
        }

        $throws = $this->nodeFinder->findInstanceOf($node->stmts, Throw_::class);
        $responses = [];

        foreach ($throws as $throw) {
            foreach ($scope->getType($throw->expr)->getObjectClassNames() as $className) {
                if (is_a($className, ApiErrorResponse::class, true)) {
                    $responses[] = $className;
                }
            }
        }

        return array_values(array_unique($responses));
    }
}
