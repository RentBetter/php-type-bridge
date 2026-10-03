<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Normalizer;

use LogicException;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Http\Include\Ref;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Priority;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Thing;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\ThingProxy;
use PTGS\TypeBridge\Tests\Normalizer\Fixtures\MarkingNormalizer;
use stdClass;
use Stringable;

final class IncludeMarkersTest extends TestCase
{
    private MarkingNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MarkingNormalizer();
    }

    public function testAnOptionalHoldsItsValueUntilResolved(): void
    {
        $calls = 0;
        $optional = $this->normalizer->exposeOptional(static function () use (&$calls): string {
            ++$calls;

            return 'computed';
        });

        self::assertSame(0, $calls, 'Nothing runs until the resolver asks');
        self::assertSame('computed', $optional->resolve());
    }

    public function testARefNamesTheEntitysClassAndId(): void
    {
        self::assertEquals(new Ref(Thing::class, 'thing-7'), $this->normalizer->exposeRef(new Thing('thing-7')));
        self::assertEquals(new Ref(ThingProxy::class, 'thing-8'), $this->normalizer->exposeRef(new ThingProxy('thing-8')), 'A proxy names its own class; the registry matches it with is_a()');
    }

    public function testARefSendsAStringableOrIntIdAsAString(): void
    {
        $uuid = new class implements Stringable {
            public function __toString(): string
            {
                return '019c0343-b15e-7d2a-8c3f-4a6b8e2f1d90';
            }
        };
        $byUuid = new class($uuid) {
            public function __construct(private readonly Stringable $id) {}

            public function getId(): Stringable
            {
                return $this->id;
            }
        };
        $byInt = new class {
            public function getId(): int
            {
                return 42;
            }
        };

        self::assertSame('019c0343-b15e-7d2a-8c3f-4a6b8e2f1d90', $this->normalizer->exposeRef($byUuid)->id);
        self::assertSame('42', $this->normalizer->exposeRef($byInt)->id);
    }

    public function testAnEntityWithoutAnIdCannotBeReferenced(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('override refId()');

        $this->normalizer->exposeRef(new stdClass());
    }

    public function testRefsMakeOneRefPerEntity(): void
    {
        $refs = $this->normalizer->exposeRefs([new Thing('thing-1'), new Thing('thing-2')]);

        self::assertSame(['thing-1', 'thing-2'], array_map(static fn (Ref $ref): string => $ref->id, $refs));
    }

    public function testAnEnumIsMarkedWithItsCase(): void
    {
        self::assertSame(CheckStatus::Warning, $this->normalizer->exposeEnum(CheckStatus::Warning)->case);
        self::assertSame(Priority::High, $this->normalizer->exposeEnum(Priority::High)->case);
    }

    public function testEachHelperGivesNullForNull(): void
    {
        self::assertSame([null, null], $this->normalizer->exposeNulls());
    }
}
