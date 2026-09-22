<div>
    <form wire:submit.prevent="save" class="option-form option-form--wide">
        @foreach ([
        'index' => ['heading' => 'Index Table Columns', 'help' => 'Changes the order of columns in the Index table. Turn fields on or off to show or hide their columns.', 'icon' => 'table', 'order' => 'indexOrder', 'fields' => 'indexFields', 'sort' => false],
        'filter' => ['heading' => 'Index Filter Fields', 'help' => 'Changes the order of fields in the Index Filter. Turn fields on or off to show or hide their filters.', 'icon' => 'filter', 'order' => 'filterOrder', 'fields' => 'filterFields', 'sort' => false],
        'sort' => ['heading' => 'Index Sort Menu', 'help' => 'Changes the order of options in the Index Filter sort menus. Turn options on or off to show or hide them.', 'icon' => 'arrow-down-wide-short', 'order' => 'sortOrder', 'fields' => 'sortFields', 'sort' => true],
        'edit' => ['heading' => 'Edit Form Fields', 'help' => 'Changes the order of fields in the Edit Details form. Turn fields on or off to show or hide them; use Editable to allow or prevent editing.', 'icon' => 'pen-to-square', 'order' => 'editOrder', 'fields' => 'editFields', 'sort' => false],
        'quick_add' => ['heading' => 'Quick Add Form Fields', 'help' => 'Changes the order of fields in the Quick Add form. Turn fields on or off to show or hide them.', 'icon' => 'file-circle-plus', 'order' => 'quickAddOrder', 'fields' => 'quickAddFields', 'sort' => false],
        'bulk_import' => ['heading' => 'Bulk Import Form Fields', 'help' => 'Changes the order of shared fields in the Bulk Import form. Values entered in visible fields are applied to every imported work.', 'icon' => 'layer-group', 'order' => 'bulkImportOrder', 'fields' => 'bulkImportFields', 'sort' => false],
        'custom_quick_add' => ['heading' => 'Custom Quick Add Form Fields', 'help' => 'Changes the order of fields in the Custom Quick Add form. Turn fields on or off to show or hide them.', 'icon' => 'file-pen', 'order' => 'customQuickAddOrder', 'fields' => 'customQuickAddFields', 'sort' => false],
    ] as $layoutProperty => $layoutConfig)
            <section class="field-layout-section">
                <h3>
                    <i class="fa-solid fa-{{ $layoutConfig['icon'] }} fa-fw options-section-icon" aria-hidden="true"></i>
                    {{ __($layoutConfig['heading']) }}
                    <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __($layoutConfig['help']) }}"
                        title="{{ __($layoutConfig['help']) }}"></i>
                </h3>

                <div class="field-layout-list" wire:sort="reorderLayout">
                    @foreach ($this->layoutRows($layoutConfig['order'], $layoutConfig['fields']) as $rowIndex => $row)
                        <div class="field-layout-row @if ($layoutConfig['sort']) field-layout-row--two-column @endif"
                            wire:key="{{ $layoutProperty }}-{{ $row['field'] }}"
                            wire:sort:item="{{ $layoutConfig['order'] }}|{{ $row['field'] }}">
                            <div class="field-layout-order">
                                <button type="button" class="field-layout-drag-handle" wire:sort:handle
                                    aria-label="{{ __('Drag :field', ['field' => $row['label']]) }}">
                                    <i class="fa-solid fa-arrows-up-down" aria-hidden="true"></i>
                                </button>
                                <div class="field-layout-buttons" wire:sort:ignore>
                                    <button type="button"
                                        wire:click.stop="move('{{ $layoutConfig['order'] }}', {{ $rowIndex }}, -1)"
                                        @disabled($rowIndex === 0)>{{ __('Up') }}</button>
                                    <button type="button"
                                        wire:click.stop="move('{{ $layoutConfig['order'] }}', {{ $rowIndex }}, 1)"
                                        @disabled($rowIndex === count($this->{$layoutConfig['order']}) - 1)>{{ __('Down') }}</button>
                                </div>
                            </div>

                            @if (!$layoutConfig['sort'] && $layoutProperty === 'index' && $row['field'] === 'title')
                                <div class="field-layout-edit-stack" wire:sort:ignore>
                                    <x-options.switch
                                        wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.visible"
                                        wrapper-class="field-layout-check field-layout-switch" :disabled="$row['visibility_locked'] ?? false">
                                        <span class="field-layout-switch-label">
                                            {{ $row['label'] }}
                                        </span>
                                        <span class="field-layout-lock-note">{{ __('Required') }}</span>
                                    </x-options.switch>

                                    <x-options.switch
                                        wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.notes_visible"
                                        wrapper-class="field-layout-check field-layout-check--edit field-layout-switch"
                                        :help="__('Shows each work\'s Notes beneath its title on the Index.')">
                                        {{ __('Notes below Title') }}
                                    </x-options.switch>
                                </div>
                            @elseif (!$layoutConfig['sort'] && $layoutProperty === 'index' && $row['field'] === 'tags')
                                <div class="field-layout-edit-stack field-layout-index-tag-buckets" wire:sort:ignore>
                                    <x-options.switch
                                        wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.custom_visible"
                                        wrapper-class="field-layout-check field-layout-check--edit field-layout-switch">
                                        {{ __('Custom Tags') }}
                                    </x-options.switch>

                                    <x-options.switch
                                        wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.fetched_visible"
                                        wrapper-class="field-layout-check field-layout-check--edit field-layout-switch">
                                        {{ __('Fetched Language Tags') }}
                                    </x-options.switch>
                                </div>
                            @else
                                <x-options.switch
                                    wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.visible"
                                    wrapper-class="field-layout-check field-layout-switch" :sort-ignore="true"
                                    :disabled="$row['visibility_locked'] ?? false" :help="$row['note'] ?? $this->fieldLayoutHelp($layoutProperty, $row['field'])">
                                    <span class="field-layout-switch-label">
                                        {{ $row['label'] }}
                                    </span>
                                    @if ($row['visibility_locked'] ?? false)
                                        <span class="field-layout-lock-note">{{ __('Required') }}</span>
                                    @endif
                                </x-options.switch>
                            @endif

                            @if (!$layoutConfig['sort'] && $layoutProperty === 'edit')
                                <x-options.switch
                                    wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.editable"
                                    wrapper-class="field-layout-check field-layout-check--edit field-layout-switch"
                                    :sort-ignore="true" :disabled="!($row['visible'] ?? false)">
                                    {{ __('Editable') }}
                                </x-options.switch>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="option-actions option-actions--inline">
                    <button type="button" class="tag tag--soft tag--lg is-clickable"
                        wire:click.preserve-scroll="saveLayout('{{ $layoutProperty }}')" wire:loading.attr="disabled"
                        wire:target="saveLayout('{{ $layoutProperty }}')">
                        {{ __('Save :layout', ['layout' => __($layoutConfig['heading'])]) }}
                    </button>
                    @if ($saved && $savedLayout === $layoutProperty)
                        <span class="saved-notice">{{ __($notice) }}</span>
                    @endif
                </div>
            </section>
        @endforeach

        <div class="option-actions option-actions--inline">
            <button type="submit"
                class="tag tag--soft tag--lg is-clickable">{{ __('Save all field layouts') }}</button>
            @if ($saved && $savedLayout === 'all')
                <span class="saved-notice">{{ __($notice) }}</span>
            @endif
            <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
                wire:click="askResetToDefault">
                {{ __('Reset to default') }}
            </button>
        </div>

        @include('livewire.partials.options-reset-confirmation-modal', [
            'open' => $confirmingResetToDefault,
            'modalId' => 'field-layouts-reset-modal',
            'message' => 'Reset this setting to its default?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
