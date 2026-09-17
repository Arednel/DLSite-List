@props([
    'initial' => false,
    'run' => null,
    'uploadMaxBytes' => 0,
])

<div class="stack transfer-upload"
    @if (!$initial) wire:key="transfer-upload-{{ $run->id }}" @endif wire:ignore
    data-transfer-upload @if ($initial) data-start-import @else data-run="{{ $run->id }}" @endif
    data-upload-max-bytes="{{ $uploadMaxBytes }}"
    data-file-too-large="{{ __('Exceeds the :size MiB upload limit.', ['size' => '__SIZE__']) }}"
    data-waiting="{{ __('Waiting') }}"
    @if ($initial) data-starting="{{ __('Starting import...') }}"
        data-start-failed="{{ __('Unable to start the import.') }}"
        data-no-files="{{ __('Choose at least one ZIP file before starting the import.') }}"
    @else
        data-no-files="{{ __('Choose at least one ZIP file.') }}"
        data-cancelled="{{ __('Transfer cancelled.') }}"
        data-url="{{ route('options.transfers.upload', $run) }}" @endif
    data-processing="{{ __('Upload complete. Waiting for server.') }}"
    data-interrupted="{{ __('Upload interrupted or inactive. Retry this file.') }}" data-uploaded="{{ __('Uploaded') }}"
    data-complete="{{ __('Uploaded. Background validation is running.') }}"
    data-failed="{{ __('Some files could not be uploaded.') }}"
    @if ($initial) data-opening="{{ __('Opening import...') }}" @endif
    data-token="{{ csrf_token() }}">
    <label class="option-field">{{ $initial ? __('ZIP parts') : __('Attach ZIP files from Archive parts list below') }}
        <input class="form-control file-upload-input" type="file" multiple accept=".zip,application/zip" data-files>
    </label>
    <div class="option-actions">
        <button class="tag tag--gradient tag--lg is-clickable" type="button" data-upload>
            {{ __('Import') }}
        </button>
    </div>
    <p class="option-description" data-status role="status" aria-live="polite"></p>
    <ul class="transfer-upload-files" data-file-status></ul>
    <div class="transfer-upload-progress" hidden data-progress>
        <div>
            <div class="progress-summary">
                <span>{{ __('Current file') }}</span>
                <span data-file-progress-value>0%</span>
            </div>
            <div class="progress-track" role="progressbar" aria-label="{{ __('Current file') }}" aria-valuemin="0"
                aria-valuemax="100" aria-valuenow="0" data-file-progress>
                <div class="progress-fill" style="width: 0%"></div>
            </div>
        </div>
        <div>
            <div class="progress-summary">
                <span>{{ __('All selected files') }}</span>
                <span data-total-progress-value>0%</span>
            </div>
            <div class="progress-track" role="progressbar" aria-label="{{ __('All selected files') }}"
                aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-total-progress>
                <div class="progress-fill" style="width: 0%"></div>
            </div>
        </div>
    </div>
</div>
