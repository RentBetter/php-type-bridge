<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericTraitFixtures\Notes;

use PTGS\TypeBridge\Status\HttpOk;

final class LabelListResponse implements HttpOk
{
    /** @use HasLabels<bool> */
    use HasLabels;
}
