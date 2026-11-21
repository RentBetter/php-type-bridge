<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use RuntimeException;

/**
 * A request contract that can't be read as far as a reader of it needs: one naming more classes and
 * aliases that name one another than are read through, or a key typed with a project-wide alias
 * whose null can't be read from its TypeScript where that null decides something. Where only a tool
 * reads it — a body's keys — it fails the tool ({@see \PTGS\TypeBridge\Model\CollectedMcpTool::$unreadable});
 * where the TypeScript does too — a query's — it fails the run.
 */
final class UnreadableContract extends RuntimeException {}
