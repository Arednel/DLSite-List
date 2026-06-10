<?php

namespace App\Livewire;

use App\Models\Genre;
use App\Models\Option;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class TagLibraryManager extends Component
{
    public string $search = '';

    public string $newTagTitle = '';

    public bool $showAllTags = false;

    public ?int $confirmingDeleteTagId = null;

    public string $notice = '';

    public function mount(): void
    {
        $this->showAllTags = Option::tagLibraryTagsExpandedByDefault();
    }

    public function render(): View
    {
        return view('livewire.tag-library-manager', [
            'genres' => $this->visibleGenres(),
            'confirmingDeleteTag' => $this->confirmingDeleteTag(),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->notice = '';

        if (filled($this->search)) {
            $this->showAllTags = true;
        }
    }

    public function toggleAllTags(): void
    {
        $this->showAllTags = ! $this->showAllTags;
        $this->notice = '';
    }

    public function createTag(): void
    {
        $title = trim($this->newTagTitle);

        if ($title === '') {
            throw ValidationException::withMessages([
                'newTagTitle' => 'Enter a tag title.',
            ]);
        }

        if (mb_strlen($title) > 255) {
            throw ValidationException::withMessages([
                'newTagTitle' => 'Tag titles may not be greater than 255 characters.',
            ]);
        }

        $existing = Genre::query()
            ->where('title_key', Genre::titleKey($title))
            ->first();

        if ($existing) {
            $this->newTagTitle = '';
            $this->search = $existing->title;
            $this->showAllTags = true;
            $this->notice = 'Tag already exists.';

            return;
        }

        $genre = Genre::resolveByTitle($title);

        $this->newTagTitle = '';
        $this->search = $genre->title;
        $this->showAllTags = true;
        $this->notice = 'Tag created.';
    }

    public function askDeleteTag(int $genreId): void
    {
        $this->notice = '';

        if (! $this->isEmptyTag($genreId)) {
            $this->confirmingDeleteTagId = null;
            $this->notice = 'Only empty tags can be deleted.';

            return;
        }

        $this->confirmingDeleteTagId = $genreId;
    }

    public function cancelDeleteTag(): void
    {
        $this->confirmingDeleteTagId = null;
    }

    public function deleteTag(): void
    {
        if ($this->confirmingDeleteTagId === null) {
            return;
        }

        $genreId = $this->confirmingDeleteTagId;
        $this->confirmingDeleteTagId = null;

        $deleted = Genre::query()
            ->whereKey($genreId)
            ->whereDoesntHave('products')
            ->delete();

        if ($deleted === 0) {
            $this->notice = 'Only empty tags can be deleted.';

            return;
        }

        $this->notice = 'Empty tag deleted.';
    }

    /**
     * @return Collection<int, object{id: int, title: string, products_count: int, pivot_count: int}>
     */
    private function visibleGenres(): Collection
    {
        $search = trim($this->search);

        $query = DB::table('genres')
            ->leftJoin('genre_product', 'genres.id', '=', 'genre_product.genre_id')
            ->leftJoin('genre_product_languages', function ($join): void {
                $join->on('genre_product_languages.genre_product_id', '=', 'genre_product.id')
                    ->where('genre_product_languages.language', Genre::LANGUAGE_ENGLISH);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('genres.title', 'like', '%' . $search . '%');
            })
            ->groupBy('genres.id', 'genres.title')
            ->havingRaw(
                'COUNT(DISTINCT CASE WHEN genre_product.source = ? OR genre_product_languages.id IS NOT NULL THEN genre_product.product_id END) > 0 OR COUNT(genre_product.id) = 0',
                [Genre::PIVOT_SOURCE_CUSTOM]
            )
            ->orderBy('genres.title')
            ->select([
                'genres.id',
                'genres.title',
                DB::raw('COUNT(genre_product.id) as pivot_count'),
            ])
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN genre_product.source = ? OR genre_product_languages.id IS NOT NULL THEN genre_product.product_id END) as products_count',
                [Genre::PIVOT_SOURCE_CUSTOM]
            );

        return $query->get()
            ->map(function (object $genre): object {
                $genre->id = (int) $genre->id;
                $genre->products_count = (int) $genre->products_count;
                $genre->pivot_count = (int) $genre->pivot_count;

                return $genre;
            });
    }

    private function confirmingDeleteTag(): ?Genre
    {
        if ($this->confirmingDeleteTagId === null) {
            return null;
        }

        return Genre::query()->find($this->confirmingDeleteTagId);
    }

    private function isEmptyTag(int $genreId): bool
    {
        return Genre::query()->whereKey($genreId)->exists()
            && ! DB::table('genre_product')->where('genre_id', $genreId)->exists();
    }
}
