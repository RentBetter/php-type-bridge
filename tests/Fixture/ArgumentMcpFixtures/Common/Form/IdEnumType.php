<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Stand-in for property-api's own EnumType: a choice type that names its enum `enum` rather
 * than `class`, and matches a submitted value against each case's id rather than its backing
 * value.
 *
 * @extends AbstractType<\UnitEnum>
 */
final class IdEnumType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_value' => 'id',
            'choices' => static function (Options $options): array {
                /** @var class-string<\UnitEnum> $enum */
                $enum = $options['enum'];

                return $enum::cases();
            },
        ]);

        $resolver->setRequired('enum');
        $resolver->setAllowedTypes('enum', 'string');
    }
}
