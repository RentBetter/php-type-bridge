<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GrammarFixtures\Catalogue\Form;

/**
 * @phpstan-type FormData = array{code: string}
 * @phpstan-type QuestionData = array{label: string}
 * @phpstan-type HelpData = array{tooltip?: array{text: string} | array{md: string}}
 * @phpstan-type LengthHints = array{good?: positive-int, great?: positive-int}
 * @phpstan-type Children = array{children: list<FormData|QuestionData>}
 * @phpstan-type Reviewers = array<class-string<\Stringable>, QuestionData>
 * @phpstan-type ScalarValue = null|scalar
 * @phpstan-type ScalarValues = array<array-key, ScalarValue>
 * @phpstan-type Row = array{int, string, ...}
 */
final class QuestionView {}
