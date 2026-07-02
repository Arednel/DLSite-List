<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class TagLibraryDisplaySettings extends Component
{
    use ConfirmsOptionReset;

    public bool $expandedByDefault = false;

    public bool $indexGroupOrderingEnabled = false;

    public array $colorSurfaces = [];

    public function mount(): void
    {
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->colorSurfaces = Option::tagColorSurfaces();
    }

    public function render(): View
    {
        return view('livewire.tag-library-display-settings', [
            'colorSurfaceLabels' => $this->colorSurfaceLabels(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'expandedByDefault' => ['boolean'],
            'indexGroupOrderingEnabled' => ['boolean'],
            'colorSurfaces.index' => ['boolean'],
            'colorSurfaces.tag_library' => ['boolean'],
            'colorSurfaces.autocomplete' => ['boolean'],
            'colorSurfaces.edit_readonly' => ['boolean'],
            'colorSurfaces.refetch' => ['boolean'],
        ]);

        Option::setTagLibraryTagsExpandedByDefault($this->expandedByDefault);
        Option::setTagLibraryIndexGroupOrderingEnabled($this->indexGroupOrderingEnabled);
        Option::setTagColorSurfaces($this->colorSurfaces);
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->colorSurfaces = Option::tagColorSurfaces();
        $this->markSaved('Tag Library settings saved.');
    }

    public function resetToDefault(): void
    {
        Option::resetTagLibraryTagsExpandedByDefaultToDefault();
        Option::resetTagLibraryIndexGroupOrderingEnabledToDefault();
        Option::resetTagColorSurfacesToDefault();
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->colorSurfaces = Option::tagColorSurfaces();
        $this->completeResetWithNotice('Tag Library settings reset to default.');
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->expandedByDefault = Option::tagLibraryTagsExpandedByDefault();
        $this->indexGroupOrderingEnabled = Option::tagLibraryIndexGroupOrderingEnabled();
        $this->colorSurfaces = Option::tagColorSurfaces();
        $this->clearSavedNotice();
    }

    public function updated(string $property): void
    {
        if (
            ! in_array($property, ['expandedByDefault', 'indexGroupOrderingEnabled'], true)
            && ! str_starts_with($property, 'colorSurfaces.')
        ) {
            return;
        }

        $this->clearSavedNotice();
    }

    public function colorSurfaceLabels(): array
    {
        return [
            Option::TAG_COLOR_SURFACE_INDEX => 'Index',
            Option::TAG_COLOR_SURFACE_TAG_LIBRARY => 'Tag Library',
            Option::TAG_COLOR_SURFACE_AUTOCOMPLETE => 'Autocomplete suggestions',
            Option::TAG_COLOR_SURFACE_EDIT_READONLY => 'Edit readonly tags',
            Option::TAG_COLOR_SURFACE_REFETCH => 'Refetch review tags',
        ];
    }
}
