<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

/**
 * A blank the form can't make a value of: a transformer fails it (TransformationFailedException),
 * so the form is invalid — a 422 — whatever its constraints say. What a field's
 * {@see CollectedFormField::$blankValue} is then, in place of a value for its constraints to judge.
 */
final readonly class FailedBlank
{
}
