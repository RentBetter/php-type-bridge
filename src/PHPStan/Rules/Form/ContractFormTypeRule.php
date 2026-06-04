<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Form;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
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
            $this->validator->validate($classReflection->getName()),
        );
    }
}
