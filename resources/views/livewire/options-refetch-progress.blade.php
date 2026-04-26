<section class="panel" @if ($run->isRunning()) wire:poll.1s="refreshProgress" @endif>
    <h2>Progress</h2>
    <div class="progress-summary">
        <span>{{ $run->processed_count }} / {{ $run->total_count }} works processed</span>
        <span>{{ ucfirst($run->status) }}</span>
    </div>
    <div class="progress-track">
        <div class="progress-fill" style="width: {{ $progressPercent }}%"></div>
    </div>
    <div class="summary-grid">
        <div>Fetched <strong>{{ $run->fetched_count }}</strong></div>
        <div>Skipped <strong>{{ $run->skipped_count }}</strong></div>
        <div>Total <strong>{{ $run->total_count }}</strong></div>
    </div>
</section>
