<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Normalizer;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PTGS\TypeBridge\Contract\ShapeNormalizer;

/**
 * Flags a `ShapeNormalizer::normalize()` call made inside a loop or an array_* callback.
 * Batch normalising belongs to the normalizer (a `normalizeMany()`/`normalizeAll()` method that
 * owns the iteration), not to a caller looping a single-item `normalize()`.
 *
 * A normalizer's own batch method legitimately loops `$this->normalize()`, so calls on `$this`
 * are exempt.
 *
 * @implements Rule<ClassMethod>
 */
final class ShapeNormalizerBatchRule implements Rule
{
    private const array ARRAY_FUNCTIONS = [
        'array_map',
        'array_filter',
        'array_walk',
        'array_reduce',
        'usort',
        'uasort',
        'uksort',
    ];

    private NodeFinder $nodeFinder;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
        $this->nodeFinder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (null === $node->stmts) {
            return [];
        }

        $errors = [];
        $seen = [];

        foreach ($this->batchContexts($node->stmts) as [$label, $subtree]) {
            foreach ($this->normalizerNormalizeCalls($subtree, $scope) as $call) {
                $id = spl_object_id($call);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;

                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'ShapeNormalizer::normalize() is called inside %s. Give the normalizer a batch method (e.g. normalizeMany()) that owns the iteration, and call that instead.',
                    $label,
                ))
                    ->identifier('typeBridge.normalizerInLoop')
                    ->line($call->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * The loop / array_* subtrees in which a per-item normalize() would be a batch smell, most
     * specific first so a call nested in an array_* inside a loop is labelled by the array_*.
     *
     * @param Node\Stmt[] $stmts
     *
     * @return list<array{0: string, 1: array<Node>}> [label, nodes to scan]
     */
    private function batchContexts(array $stmts): array
    {
        $contexts = [];

        foreach ($this->nodeFinder->findInstanceOf($stmts, FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && \in_array($call->name->toLowerString(), self::ARRAY_FUNCTIONS, true)) {
                $contexts[] = [\sprintf('an %s() callback', $call->name->toString()), $call->getArgs()];
            }
        }

        $loops = $this->nodeFinder->find(
            $stmts,
            static fn (Node $candidate): bool => $candidate instanceof Foreach_
                || $candidate instanceof For_
                || $candidate instanceof While_
                || $candidate instanceof Do_,
        );
        foreach ($loops as $loop) {
            /** @var Foreach_|For_|While_|Do_ $loop */
            $contexts[] = ['a loop', $loop->stmts];
        }

        return $contexts;
    }

    /**
     * @param array<Node> $subtree
     *
     * @return list<MethodCall>
     */
    private function normalizerNormalizeCalls(array $subtree, Scope $scope): array
    {
        $calls = [];

        foreach ($this->nodeFinder->findInstanceOf($subtree, MethodCall::class) as $call) {
            if (!$call->name instanceof Node\Identifier || 'normalize' !== $call->name->toString()) {
                continue;
            }

            // The normalizer's own batch method legitimately loops $this->normalize().
            if ($call->var instanceof Variable && 'this' === $call->var->name) {
                continue;
            }

            if (!$this->isShapeNormalizer($scope->getType($call->var))) {
                continue;
            }

            $calls[] = $call;
        }

        return $calls;
    }

    private function isShapeNormalizer(Type $type): bool
    {
        foreach ($type->getObjectClassNames() as $className) {
            if (!$this->reflectionProvider->hasClass($className)) {
                continue;
            }

            if ($this->reflectionProvider->getClass($className)->implementsInterface(ShapeNormalizer::class)) {
                return true;
            }
        }

        return false;
    }
}
