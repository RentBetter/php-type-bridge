<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Form;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\PHPStan\Support\FormContractValidator;

/**
 * @implements Rule<InClassNode>
 */
final class ContractFormTypeRule implements Rule
{
    public function __construct(
        private readonly FormContractValidator $validator = new FormContractValidator(),
    ) {}

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if (!$classReflection->implementsInterface(ContractFormType::class)) {
            return [];
        }

        // Abstract contract-form bases (e.g. AbstractFormType) declare the data type as a
        // template variable, not a concrete class, and carry no data_class. Only their
        // concrete subclasses are real contracts to validate.
        if ($classReflection->isAbstract()) {
            return [];
        }

        $line = $node->getOriginalNode()->getStartLine();

        return array_map(
            static fn (string $message) => RuleErrorBuilder::message($message)
                ->identifier('typeBridge.contractForm')
                ->line($line)
                ->build(),
            $this->validator->validate($classReflection->getName(), self::dataClassOf($classReflection)),
        );
    }

    /**
     * The class bound to ContractFormType's TData, however the form got there.
     *
     * PHPStan has already resolved this. `getAncestorWithClassName()` walks to the interface
     * through the whole hierarchy and returns it with its template types substituted, so every
     * spelling — `at-extends`, `at-implements`, the `phpstan-` prefixed variants, an abstract
     * base that forwards the variable — plus imported short names and nested generics are all
     * handled by the same resolution the rest of the analysis uses.
     *
     * This used to be a regex over the leaf class's raw docblock, which matched one spelling
     * only and, having thrown away the import context a parser gives for free, then needed a
     * second regex over the file's `use` statements to turn a short name back into a class. It
     * could not see a form that inherited the contract from a base — the shape almost every
     * application uses.
     *
     * @return class-string|null
     */
    private static function dataClassOf(ClassReflection $classReflection): ?string
    {
        $ancestor = $classReflection->getAncestorWithClassName(ContractFormType::class);

        if (null === $ancestor) {
            return null;
        }

        $bound = $ancestor->getActiveTemplateTypeMap()->getType('TData');

        if (null === $bound) {
            return null;
        }

        $classNames = $bound->getObjectClassNames();

        // Exactly one, or the binding is a union or unresolved and there is nothing to check.
        if (1 !== \count($classNames) || !class_exists($classNames[0])) {
            return null;
        }

        return $classNames[0];
    }
}
