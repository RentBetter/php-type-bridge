<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Controller;

use PhpParser\Node;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Contract\ApiErrorResponse;
use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\PHPStan\Support\ApiMethodInspector;

/**
 * @implements Rule<ClassMethod>
 */
final class ApiResponseDeclarationRule implements Rule
{
    private NodeFinder $nodeFinder;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
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
        if (!$classReflection instanceof ClassReflection) {
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
        if (!$classReflection->hasMethod($methodName)) {
            return [];
        }

        $methodReflection = $classReflection->getMethod($methodName, $scope);

        foreach ($this->returnedResponseClasses($methodReflection) as $responseClass) {
            if (isset($declared[$responseClass])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'API controller method "%s::%s" returns "%s" but it is missing from #[ApiResponses].',
                $className,
                $methodName,
                $responseClass,
            ))
                ->identifier('typeBridge.apiResponseReturnUndeclared')
                ->line($node->getStartLine())
                ->build();
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
            ))
                ->identifier('typeBridge.apiResponseThrowUndeclared')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function returnedResponseClasses(ExtendedMethodReflection $methodReflection): array
    {
        $variant = $methodReflection->getVariants()[0] ?? null;
        if (null === $variant) {
            return [];
        }

        $returnType = $variant->getReturnType();
        $responses = [];
        foreach ($returnType->getObjectClassNames() as $className) {
            if (!$this->reflectionProvider->hasClass($className)) {
                continue;
            }

            $classReflection = $this->reflectionProvider->getClass($className);
            if ($classReflection->implementsInterface(ApiResponse::class)) {
                $responses[] = $classReflection->getName();
            }
        }

        return array_values(array_unique($responses));
    }

    /**
     * @return list<string>
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
                if (!$this->reflectionProvider->hasClass($className)) {
                    continue;
                }

                $classReflection = $this->reflectionProvider->getClass($className);
                if ($classReflection->implementsInterface(ApiErrorResponse::class)) {
                    $responses[] = $classReflection->getName();
                }
            }
        }

        return array_values(array_unique($responses));
    }
}
