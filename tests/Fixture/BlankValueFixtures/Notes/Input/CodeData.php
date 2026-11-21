<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CodeData
{
    #[Assert\Regex('/^[A-Z]+$/')]
    public ?string $code = null;

    #[Assert\Regex('/^[a-z]+$/')]
    public ?string $label = null;
}
