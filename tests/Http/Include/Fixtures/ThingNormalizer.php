<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include\Fixtures;

use PTGS\TypeBridge\Http\Include\Optional;
use PTGS\TypeBridge\Http\Include\Ref;
use PTGS\TypeBridge\Http\Include\RefNormalizer;

/**
 * thing-1 points at thing-2, and thing-2 back at thing-1; any other id is not found. Records
 * every fetch.
 */
final class ThingNormalizer implements RefNormalizer
{
    private const array NEXT = ['thing-1' => 'thing-2', 'thing-2' => 'thing-1'];

    /** @var list<list<string>> */
    public array $fetched = [];

    public static function entityClass(): string
    {
        return Thing::class;
    }

    public function resolveMany(array $ids): array
    {
        $this->fetched[] = $ids;

        $records = [];
        foreach ($ids as $id) {
            if (isset(self::NEXT[$id])) {
                $records[] = [
                    'id' => $id,
                    'name' => "Thing {$id}",
                    'next' => new Ref(Thing::class, self::NEXT[$id]),
                    'debug' => new Optional(static fn (): array => ['seen' => $id]),
                ];
            }
        }

        return $records;
    }
}
