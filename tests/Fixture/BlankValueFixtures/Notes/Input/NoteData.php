<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class NoteData
{
    public ?string $title = null;

    public ?string $code = null;

    public ?int $count = null;

    /** @var list<string|null> */
    #[Assert\All([new Assert\NotNull()])]
    public array $tags = [];
    /** @var list<string|null> */
    public array $labels = [];
    /** @var list<string|null> */
    public array $notes = [];
}
