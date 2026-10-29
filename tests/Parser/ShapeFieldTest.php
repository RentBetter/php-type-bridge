<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;

/**
 * A key may be left out when it is optional (`key?: T`) or valued `?T` — which the TypeScript emits
 * `key?: T` and the wire leaves off when null — and must be sent otherwise, `T|null` included.
 */
final class ShapeFieldTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function keys(): iterable
    {
        yield 'key: T' => ['array{key: string}', false];
        yield 'key?: T' => ['array{key?: string}', true];
        yield 'key: ?T' => ['array{key: ?string}', true];
        yield 'key?: ?T' => ['array{key?: ?string}', true];
        yield 'key: T|null' => ['array{key: string|null}', false];
        yield 'key?: T|null' => ['array{key?: string|null}', true];
        yield "key: 'a'|'b'|null" => ["array{key: 'a'|'b'|null}", false];
    }

    #[DataProvider('keys')]
    public function test_a_key_may_be_left_out_when_the_typescript_makes_it_optional(string $shape, bool $mayBeLeftOut): void
    {
        $parsed = (new PhpDocShapeParser())->parse($shape);
        self::assertInstanceOf(ShapeType::class, $parsed);

        self::assertSame($mayBeLeftOut, $parsed->fields[0]->mayBeLeftOut());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function values(): iterable
    {
        yield 'T' => ['array{key: string}', false];
        yield 'T|null' => ['array{key: string|null}', true];
        yield "'a'|'b'|null" => ["array{key: 'a'|'b'|null}", true];
        yield 'mixed' => ['array{key: mixed}', true];
        yield 'T|mixed, which is mixed' => ['array{key: string|int|mixed}', true];
        yield '?T, a key left out when null' => ['array{key: ?string}', false];
    }

    #[DataProvider('values')]
    public function test_a_value_sent_may_be_null_where_its_type_admits_one(string $shape, bool $admitsNull): void
    {
        $parsed = (new PhpDocShapeParser())->parse($shape);
        self::assertInstanceOf(ShapeType::class, $parsed);

        self::assertSame($admitsNull, ShapeField::admitsNull($parsed->fields[0]->type));
    }
}
