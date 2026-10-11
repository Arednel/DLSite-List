<dialog id="index-image-viewer-dialog" class="index-image-viewer" aria-labelledby="index-image-viewer-title" wire:ignore
    data-image-label="{{ __('Image :current of :total for :title') }}">
    <div class="index-image-viewer__panel">
        <header class="index-image-viewer__header">
            <h2 id="index-image-viewer-title" data-index-image-viewer-title>{{ __('Cover and Sample Images') }}</h2>
            <button type="button" class="index-image-viewer__close" data-index-image-viewer-close
                aria-label="{{ __('Close image viewer') }}">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="index-image-viewer__stage">
            <button type="button" class="index-image-viewer__navigation index-image-viewer__navigation--previous"
                data-index-image-viewer-previous aria-label="{{ __('Previous image') }}">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>

            <div class="index-image-viewer__media">
                <img data-index-image-viewer-image hidden alt="">
                <div class="index-image-viewer__placeholder" data-index-image-viewer-placeholder hidden role="status">
                    <i class="fa-regular fa-image" aria-hidden="true"></i>
                    <span>{{ __('No image') }}</span>
                </div>
                <p class="index-image-viewer__loading" data-index-image-viewer-loading role="status">
                    {{ __('Loading images...') }}
                </p>
            </div>

            <button type="button" class="index-image-viewer__navigation index-image-viewer__navigation--next"
                data-index-image-viewer-next aria-label="{{ __('Next image') }}">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <footer class="index-image-viewer__footer">
            <output class="index-image-viewer__counter" data-index-image-viewer-counter aria-live="polite"></output>
            <a class="index-image-viewer__view-full" data-index-image-viewer-full target="_blank"
                rel="noopener noreferrer" hidden>
                {{ __('View in full') }}
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            </a>
        </footer>
    </div>
</dialog>
