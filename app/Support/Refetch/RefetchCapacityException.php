<?php

namespace App\Support\Refetch;

use RuntimeException;

/**
 * Thrown when a new refetch run would be queued while another run is still fetching works.
 */
final class RefetchCapacityException extends RuntimeException {}
