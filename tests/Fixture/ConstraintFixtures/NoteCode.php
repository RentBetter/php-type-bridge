<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ConstraintFixtures;

use Attribute;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * A project's own rule made of others: a note's code is upper-case letters, never blank. Each of
 * them holds wherever the rule is put, as if written there itself.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class NoteCode extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [new NotBlank(), new Regex('/^[A-Z]+$/D')];
    }
}
