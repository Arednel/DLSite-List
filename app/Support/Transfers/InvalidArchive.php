<?php

namespace App\Support\Transfers;

use RuntimeException;

/** Untrusted archive structure or integrity cannot be repaired by a worker retry. */
final class InvalidArchive extends RuntimeException {}
