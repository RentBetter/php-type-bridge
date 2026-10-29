<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Shape;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Attribute\ValueOfName;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Support\ClassNames;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use Throwable;

/**
 * Enforces the TypeBridge `preserveNull` invariant on @phpstan-type shapes.
 *
 * For each nullable field declared in a @phpstan-type alias on a class or an enum, under the name
 * its shape is emitted as:
 *
 *   - If the field is listed in `preserveNull` config, its annotation MUST be `T|null`
 *     (will emit as `field: T | null`).
 *   - Otherwise its annotation MUST be `?T` (will emit as `field?: T`).
 *
 * The runtime emitter applies the same check; this rule surfaces drift at analysis time
 * before it can ship as a broken codegen.
 *
 * @implements Rule<ClassLike>
 */
final class PreserveNullConsistencyRule implements Rule
{
    /** @var array<string, true> indexed by "ShapeName.fieldName" */
    private readonly array $preserveNullIndex;

    /**
     * @param list<string> $preserveNull list of "ShapeName.fieldName" entries
     */
    public function __construct(
        array $preserveNull = [],
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
    ) {
        $this->preserveNullIndex = array_fill_keys($preserveNull, true);
    }

    public function getNodeType(): string
    {
        return ClassLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_ && !$node instanceof Enum_) {
            return [];
        }

        $docComment = $node->getDocComment();
        if (null === $docComment) {
            return [];
        }

        $definitions = $this->docHelper->extractPhpStanTypes($docComment->getText());
        if ([] === $definitions) {
            return [];
        }

        $ownerClass = $node->namespacedName?->toString()
            ?? $scope->getClassReflection()?->getName()
            ?? ($node->name->name ?? '<anonymous>');
        $startLine = $node->getStartLine();

        $errors = [];
        foreach ($definitions as $alias => $raw) {
            try {
                $parsed = $this->parser->parse($raw);
            } catch (Throwable) {
                continue;
            }

            $shape = ShapeType::declaredBy($parsed);
            if (null === $shape) {
                continue;
            }

            $shapeName = '_self' === $alias ? PhpDocTypeHelper::selfTypeName(ClassNames::short($ownerClass), $node instanceof Enum_, self::namesItsValues($node, $scope)) : $alias;
            foreach ($shape->fields as $field) {
                $error = $this->checkField($field, $shapeName, $ownerClass, $startLine);
                if (null !== $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * Whether the declaration names its value union otherwise, with a {@see ValueOfName}: read off its
     * attributes, as the source is analysed rather than loaded.
     */
    private static function namesItsValues(Class_|Enum_ $node, Scope $scope): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (ValueOfName::class === $scope->resolveName($attribute->name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function checkField(ShapeField $field, string $shapeName, string $ownerClass, int $line): ?\PHPStan\Rules\IdentifierRuleError
    {
        if (!$field->type instanceof NullableType) {
            return null;
        }

        $key = $shapeName . '.' . $field->name;
        $inPreserveNull = isset($this->preserveNullIndex[$key]);

        if ($inPreserveNull && $field->type->optional) {
            return RuleErrorBuilder::message(\sprintf(
                'Field `%s` in shape `%s` (on %s) is listed in `typeBridge.preserveNull` '
                . 'but its @phpstan-type annotation is `?T`. Change the annotation to '
                . '`T|null` so the wire emits the null, or remove `%s` from preserveNull.',
                $field->name,
                $shapeName,
                $ownerClass,
                $key,
            ))
                ->identifier('typeBridge.preserveNull.shouldBeExplicitNull')
                ->line($line)
                ->build();
        }

        if (!$inPreserveNull && !$field->type->optional) {
            return RuleErrorBuilder::message(\sprintf(
                'Field `%s` in shape `%s` (on %s) has `T|null` annotation but is not '
                . 'listed in `typeBridge.preserveNull`. Change the annotation to `?T` '
                . '(emits as `%s?: T`; field is omitted when null), or add `%s` to '
                . 'preserveNull if null is semantically meaningful for this field.',
                $field->name,
                $shapeName,
                $ownerClass,
                $field->name,
                $key,
            ))
                ->identifier('typeBridge.preserveNull.shouldBeOptional')
                ->line($line)
                ->build();
        }

        return null;
    }
}
