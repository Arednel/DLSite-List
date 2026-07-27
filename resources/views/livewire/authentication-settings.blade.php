<div class="authentication-settings">
    <form wire:submit.prevent="saveSettings" class="option-form authentication-settings-form">
        <x-options.switch wire:model.live="enabled" :help="__('When enabled, all application pages and actions require the administrator account.')">
            {{ __('Require administrator login') }}
        </x-options.switch>

        <div class="authentication-account-status">
            <strong>{{ __('Account status:') }}</strong>
            <span>
                {{ $accountExists ? __('Administrator account exists') : __('No administrator account exists') }}
            </span>
        </div>

        <fieldset class="option-fieldset">
            <legend>{{ __('Authentication Page Theme') }}</legend>
            <div class="option-radio-grid">
                @foreach ($themeOptions as $value => $label)
                    <label class="option-radio">
                        <input type="radio" wire:model.live="theme" value="{{ $value }}">
                        <span>{{ __($label) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @error('enabled')
            <div class="text-error">{{ $message }}</div>
        @enderror
        @error('theme')
            <div class="text-error">{{ $message }}</div>
        @enderror

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">
                {{ __('Save authentication settings') }}
            </button>
            @if ($saved)
                <span class="saved-notice">{{ __($notice) }}</span>
            @endif
        </div>
    </form>

    @auth('web')
        <div class="authentication-password-section">
            <h2>{{ __('Change Administrator Password') }}</h2>
            <p class="option-description">
                {{ __('Changing the password signs out this browser and invalidates other sessions and remember-me cookies.') }}
            </p>

            <form wire:submit.prevent="changePassword" class="option-form">
                <label class="option-field">
                    <span>{{ __('New password') }}</span>
                    <input type="password" wire:model="newPassword" autocomplete="new-password">
                </label>
                @error('newPassword')
                    <div class="text-error">{{ $message }}</div>
                @enderror

                <label class="option-field">
                    <span>{{ __('Confirm new password') }}</span>
                    <input type="password" wire:model="newPasswordConfirmation" autocomplete="new-password">
                </label>
                @error('newPasswordConfirmation')
                    <div class="text-error">{{ $message }}</div>
                @enderror

                <div class="option-actions option-actions--inline">
                    <button type="submit" class="tag tag--soft tag--lg is-clickable"
                        wire:confirm="{{ __('Are you sure you want to change the administrator password?') }}">
                        {{ __('Save new password') }}
                    </button>
                </div>
            </form>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="option-actions option-actions--inline">
            @csrf
            <button type="submit" class="tag tag--soft tag--lg is-clickable">
                {{ __('Logout') }}
            </button>
        </form>
    @endauth
</div>
