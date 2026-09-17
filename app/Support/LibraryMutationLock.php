<?php

namespace App\Support;

use App\Models\RefetchRun;
use Closure;
use Illuminate\Support\Facades\Cache;

final class LibraryMutationLock
{
    public function ttl(): int
    {
        return max(RefetchRun::LIFECYCLE_LOCK_SECONDS, (int) config('transfers.lock_seconds'));
    }

    public function run(Closure $callback): mixed
    {
        return Cache::lock(RefetchRun::LIFECYCLE_LOCK, $this->ttl())->block(0, $callback);
    }
}
