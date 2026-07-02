<div>
    <form wire:submit.prevent="save" class="option-form option-form--wide">
        @foreach ([
        'index' => ['heading' => 'Index Table Fields', 'order' => 'indexOrder', 'fields' => 'indexFields', 'sort' => false],
        'filter' => ['heading' => 'Index Filter Fields', 'order' => 'filterOrder', 'fields' => 'filterFields', 'sort' => false],
        'sort' => ['heading' => 'Index Sort Menu', 'order' => 'sortOrder', 'fields' => 'sortFields', 'sort' => true],
        'edit' => ['heading' => 'Edit Form Fields', 'order' => 'editOrder', 'fields' => 'editFields', 'sort' => false],
        'quick_add' => ['heading' => 'Quick Add Form Fields', 'order' => 'quickAddOrder', 'fields' => 'quickAddFields', 'sort' => false],
        'custom_quick_add' => ['heading' => 'Custom Quick Add Form Fields', 'order' => 'customQuickAddOrder', 'fields' => 'customQuickAddFields', 'sort' => false],
    ] as $layoutProperty => $layoutConfig)
            <section class="field-layout-section">
                <h3>{{ $layoutConfig['heading'] }}</h3>

                <div class="field-layout-list" wire:sort="reorderLayout">
                    @foreach ($this->layoutRows($layoutConfig['order'], $layoutConfig['fields']) as $rowIndex => $row)
                        <div class="field-layout-row @if ($layoutConfig['sort']) field-layout-row--two-column @endif"
                            wire:key="{{ $layoutProperty }}-{{ $row['field'] }}"
                            wire:sort:item="{{ $layoutConfig['order'] }}|{{ $row['field'] }}">
                            <div class="field-layout-order">
                                <button type="button" class="field-layout-drag-handle" wire:sort:handle
                                    aria-label="Drag {{ $row['label'] }}">
                                    <i class="fa-solid fa-arrows-up-down" aria-hidden="true"></i>
                                </button>
                                <div class="field-layout-buttons" wire:sort:ignore>
                                    <button type="button"
                                        wire:click.stop="move('{{ $layoutConfig['order'] }}', {{ $rowIndex }}, -1)"
                                        @disabled($rowIndex === 0)>Up</button>
                                    <button type="button"
                                        wire:click.stop="move('{{ $layoutConfig['order'] }}', {{ $rowIndex }}, 1)"
                                        @disabled($rowIndex === count($this->{$layoutConfig['order']}) - 1)>Down</button>
                                </div>
                            </div>

                            <label class="field-layout-check" wire:sort:ignore>
                                <input type="checkbox"
                                    wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.visible"
                                    @disabled($row['visibility_locked'] ?? false)>
                                <span>
                                    {{ $row['label'] }}
                                    @if ($row['note'] ?? false)
                                        <span class="field-layout-note">{{ $row['note'] }}</span>
                                    @endif
                                </span>
                                @if ($row['visibility_locked'] ?? false)
                                    <span class="field-layout-lock-note">Required</span>
                                @endif
                            </label>

                            @if (!$layoutConfig['sort'] && $layoutProperty === 'edit' && $row['field'] === 'tags')
                                <div class="field-layout-edit-stack" wire:sort:ignore>
                                    <label class="field-layout-check field-layout-check--edit">
                                        <input type="checkbox"
                                            wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.editable"
                                            @disabled(!($row['visible'] ?? false))>
                                        <span>Editable Custom Tags</span>
                                    </label>

                                    <label class="field-layout-check field-layout-check--edit">
                                        <input type="checkbox"
                                            wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.fetched_editable"
                                            @disabled(!($row['visible'] ?? false))>
                                        <span>Editable Fetched EN Tags</span>
                                    </label>
                                </div>
                            @elseif (!$layoutConfig['sort'] && $layoutProperty === 'edit')
                                <label class="field-layout-check field-layout-check--edit" wire:sort:ignore>
                                    <input type="checkbox"
                                        wire:model.live="{{ $layoutConfig['fields'] }}.{{ $row['field'] }}.editable"
                                        @disabled(!($row['visible'] ?? false))>
                                    <span>Editable</span>
                                </label>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">Save field layouts</button>
            <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
                wire:click="askResetToDefault">
                Reset to default
            </button>
            @if ($saved)
                <span class="saved-notice">{{ $notice }}</span>
            @endif
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
