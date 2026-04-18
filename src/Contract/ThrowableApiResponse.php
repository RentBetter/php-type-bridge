<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Contract;

abstract class ThrowableApiResponse extends \RuntimeException implements ApiErrorResponse {}
