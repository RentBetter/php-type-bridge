<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Shape;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Shape\ShapeRenderer;

final class ShapeRendererTest extends TestCase
{
    public function testRendersASelfDocBlock(): void
    {
        $shape = new ShapeType([
            new ShapeField('name', new ScalarType('string'), optional: false),
            new ShapeField('status', new ValueOfType('TaskStatus'), optional: false),
            new ShapeField('dueDate', new NullableType(new ScalarType('string'), optional: true), optional: false),
            new ShapeField('tags', new ListType(new ScalarType('string')), optional: true),
        ]);

        self::assertSame(
            <<<'DOC'
                /**
                 * @phpstan-type _self = array{
                 *     name: string,
                 *     status: value-of<TaskStatus>,
                 *     dueDate: ?string,
                 *     tags?: list<string>,
                 * }
                 */
                DOC,
            (new ShapeRenderer())->renderSelfDocBlock($shape),
        );
    }

    public function testDistinguishesOptionalNullFromMeaningfulNull(): void
    {
        // ?T is the TS-optional form and T|null the meaningful-null one. Collapsing them here
        // would silently change the wire contract of every field it touched.
        $renderer = new ShapeRenderer();

        self::assertSame('?string', $renderer->render(new NullableType(new ScalarType('string'), optional: true)));
        self::assertSame('string|null', $renderer->render(new NullableType(new ScalarType('string'), optional: false)));
    }

    public function testRenderedShapesParseBackToAnEquivalentTree(): void
    {
        // The renderer is the parser's inverse, and generated shapes get written into source
        // files — anything it emits must be something the parser accepts.
        $source = <<<'PHP'
            <?php
            /**
             * @phpstan-type _self = array{
             *     name: string,
             *     count: int,
             *     status: value-of<TaskStatus>,
             *     tags: list<string>,
             *     note: ?string,
             *     archivedAt: string|null,
             *     nested: array{id: string, label?: string},
             * }
             */
            class Example {}
            PHP;

        $parser = new PhpDocShapeParser();
        $original = $parser->parse($this->selfBody($source));
        $rendered = (new ShapeRenderer())->render($original);

        self::assertEquals($original, $parser->parse($rendered));
    }

    private function selfBody(string $source): string
    {
        preg_match('/@phpstan-type\s+_self\s*=\s*(array\{.*?\n\s*\*\s*\})/s', $source, $matches);
        self::assertNotEmpty($matches, 'fixture must contain a _self shape');

        return preg_replace('/^\s*\*\s?/m', '', $matches[1]) ?? '';
    }
}
