<?php

namespace App\Support\BulkImport;

use RuntimeException;

/**
 * Thrown when starting another Bulk Import would exceed the active-run or queued-work budget.
 */
final class BulkImportCapacityException extends RuntimeException {}
