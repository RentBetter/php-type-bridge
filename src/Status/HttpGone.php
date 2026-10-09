<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Status;

use PTGS\TypeBridge\Contract\ApiErrorResponse;

/**
 * 410: what the request names existed and is gone for good — an expired or spent one-time token,
 * say. Unlike a 404 it tells the client not to try again with the same thing.
 */
interface HttpGone extends ApiErrorResponse {}
