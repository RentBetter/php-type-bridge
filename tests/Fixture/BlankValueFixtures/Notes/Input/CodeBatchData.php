<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Input;

final class CodeBatchData
{
    public ?CodeData $code = null;

    /** @var list<CodeData> */
    public array $codes = [];

    /** @var list<CodeData> */
    public array $plainCodes = [];

    /** @var list<CodeData> */
    public array $listedCodes = [];
}
