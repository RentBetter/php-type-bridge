<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Shape;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Support\PhpDocShapeParserResolver;

/**
 * Flags `_self` shapes that contain both `entityType` and `entityId` fields as a pair.
 *
 * TypeBridge contract: polymorphic entity references should be a single compound field
 * `entity: "{type}-{uuid}"` (e.g. `"stage-abc-123…"`). Splitting into two fields loses
 * the symmetry with request shapes (URL parameters, form bodies) and duplicates
 * parsing logic across every consumer.
 *
 * @implements Rule<Class_>
 */
final class NoTypeIdPairRule implements Rule
{
    public function __construct(
        private readonly PhpDocShapeParserResolver $resolver = new PhpDocShapeParserResolver(),
    ) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $shape = $this->resolver->resolveSelfShape($node);
        if (null === $shape) {
            return [];
        }

        $fieldNames = array_map(static fn ($field) => $field->name, $shape->fields);
        if (!\in_array('entityType', $fieldNames, strict: true) || !\in_array('entityId', $fieldNames, strict: true)) {
            return [];
        }

        $className = $node->namespacedName?->toString() ?? $scope->getClassReflection()?->getName() ?? ($node->name->name ?? '<anonymous>');

        return [RuleErrorBuilder::message(\sprintf(
            'Shape `_self` on %s must not pair `entityType` and `entityId`. '
                . 'Use a single compound `entity: "{type}-{uuid}"` field instead — '
                . 'the request side (URL/body) uses the same `"{type}-{uuid}"` format, and the compound '
                . 'keeps a single parse helper across wire and store.',
            $className,
        ))
            ->identifier('typeBridge.shapeNaming.noTypeIdPair')
            ->line($node->getStartLine())
            ->build()];
    }
}
