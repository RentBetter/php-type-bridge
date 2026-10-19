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
    #[Assert\All([new Assert\NotNull(groups: ['strict']), new Assert\Length(max: 50)])]
    public array $notes = [];

    /** @var list<string|null> */
    #[Assert\All([new Assert\NotBlank(allowNull: true)])]
    public array $codes = [];

    #[Assert\Regex('/^[A-Z]+-[0-9]+$/')]
    public ?string $ref = null;

    public bool $pinned = false;

    /** @var list<string> */
    public array $kinds = [];

    public ?string $status = null;

    #[Assert\Regex('/^https?:\/\/[a-z.]+$/')]
    public ?string $site = null;

    #[Assert\Regex('/^[a-z-]+$/')]
    public ?string $slug = null;

    /** @var list<bool> */
    public array $flags = [];

    #[Assert\Sequentially([new Assert\Choice(choices: ['bug', 'idea'])])]
    public ?string $kind = null;

    /** @var list<string|null> */
    #[Assert\Sequentially([new Assert\All([new Assert\NotNull()])])]
    public array $refs = [];

    public ?string $fallback = null;

    public ?int $quantity = null;

    /** @var list<string> */
    public array $aliases = [];
}
