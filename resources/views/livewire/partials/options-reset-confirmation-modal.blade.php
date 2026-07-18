@if ($open)
    @teleport('body')
        <div class="options-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title"
            wire:click.self="{{ $cancelAction }}" x-data="{
                remaining: {{ $this->resetConfirmDelaySeconds() }},
                ready: {{ $this->resetConfirmDelaySeconds() > 0 ? 'false' : 'true' }},
                timer: null,
                startDelay() {
                    if (this.ready) {
                        return;
                    }
            
                    this.timer = setInterval(() => {
                        this.remaining = Math.max(0, this.remaining - 1);
            
                        if (this.remaining === 0) {
                            this.ready = true;
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                    }, 1000);
                },
                destroy() {
                    if (this.timer) {
                        clearInterval(this.timer);
                    }
                },
            }" x-init="startDelay()"
            wire:keydown.escape.window="{{ $cancelAction }}">
            <div class="options-modal-card">
                <h3 id="{{ $modalId }}-title">{{ __('Are you sure?') }}</h3>
                <p>{{ __($message) }}</p>

                <div class="option-actions option-actions--modal">
                    <button type="button" class="tag tag--soft tag--lg is-clickable options-modal-cancel"
                        wire:click="{{ $cancelAction }}">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button"
                        class="tag tag--soft tag--lg is-clickable options-modal-confirm options-modal-confirm--danger"
                        wire:click="{{ $confirmAction }}" data-options-reset-confirm
                        data-options-reset-delay="{{ $this->resetConfirmDelaySeconds() }}"
                        @if ($this->resetConfirmDelaySeconds() > 0) disabled x-bind:disabled="!ready" @endif>
                        @if ($this->resetConfirmDelaySeconds() > 0)
                            <span x-show="!ready">{{ __($confirmLabel) }} (<span
                                    x-text="remaining">{{ $this->resetConfirmDelaySeconds() }}</span>)</span>
                            <span x-show="ready" x-cloak>{{ __($confirmLabel) }}</span>
                        @else
                            {{ __($confirmLabel) }}
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endteleport
@endif
