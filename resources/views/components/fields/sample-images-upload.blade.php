<tr>
    <td width="130" class="form-table-cell image-editor-row-label">{{ __('Sample Images') }}</td>
    <td class="form-table-cell image-editor-row-content">
        <div class="image-editor-field" data-product-image-editor data-product-image-editor-kind="samples">
            @if ($editable && $product !== null)
                <input type="hidden" name="sample_images_submitted" value="1">
            @endif
            <div class="image-editor-grid" data-image-editor-sample-list>
                @foreach ($samples as $sample)
                    <article class="image-editor-card" data-image-editor-card>
                        @if ($editable)
                            <div class="image-editor-card__toolbar">
                                <button class="image-editor-drag-handle" type="button" draggable="true"
                                    data-image-editor-drag-handle aria-label="{{ __('Drag image to reorder') }}"
                                    title="{{ __('Drag to reorder') }}">≡</button>
                                <button class="image-editor-remove-icon" type="button" data-image-editor-remove-sample
                                    aria-label="{{ __('Remove sample image') }}">×</button>
                            </div>
                            <input type="hidden" name="sample_image_order[]" value="existing:{{ $sample['path'] }}"
                                data-image-editor-order>
                        @endif

                        <button type="button" class="image-editor-thumb index-image-viewer-trigger"
                            data-product-image-editor-viewer data-index-image-viewer-source="{{ $sample['url'] }}"
                            data-index-image-viewer-title="{{ $viewerTitle }}" aria-haspopup="dialog"
                            aria-controls="index-image-viewer-dialog" aria-label="{{ __('Preview sample image') }}">
                            <img src="{{ $sample['url'] }}" alt="">
                        </button>

                        <div class="image-editor-card__meta">
                            <span class="image-editor-card__name"
                                title="{{ $sample['name'] }}">{{ $sample['name'] }}</span>
                            <span class="image-editor-card__position"
                                data-image-editor-position>{{ $loop->iteration }}</span>
                        </div>
                    </article>
                @endforeach

                @if ($editable)
                    <div class="image-editor-native-picker image-editor-native-picker--samples"
                        data-image-editor-add-card>
                        <span class="image-editor-native-picker__plus" aria-hidden="true">+</span>
                        <input id="sample_images" name="sample_images[]" class="image-editor-native-input"
                            type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp" multiple
                            data-image-editor-sample-input>
                    </div>
                @elseif ($samples === [])
                    <span class="image-editor-empty">-</span>
                @endif
            </div>

            @if ($editable)
                <template data-image-editor-sample-template>
                    <article class="image-editor-card" data-image-editor-card>
                        <div class="image-editor-card__toolbar">
                            <button class="image-editor-drag-handle" type="button" draggable="true"
                                data-image-editor-drag-handle aria-label="{{ __('Drag image to reorder') }}"
                                title="{{ __('Drag to reorder') }}">≡</button>
                            <button class="image-editor-remove-icon" type="button" data-image-editor-remove-sample
                                aria-label="{{ __('Remove sample image') }}">×</button>
                        </div>
                        <input type="hidden" name="sample_image_order[]" data-image-editor-order>
                        <span data-image-editor-file-slot></span>
                        <button type="button" class="image-editor-thumb index-image-viewer-trigger"
                            data-product-image-editor-viewer data-index-image-viewer-source=""
                            data-index-image-viewer-title="{{ $viewerTitle }}" aria-haspopup="dialog"
                            aria-controls="index-image-viewer-dialog" aria-label="{{ __('Preview sample image') }}">
                            <img src="" alt="">
                        </button>
                        <div class="image-editor-card__meta">
                            <span class="image-editor-card__name"></span>
                            <span class="image-editor-card__position" data-image-editor-position></span>
                        </div>
                    </article>
                </template>
            @endif

            @error('sample_images')
                <div class="text-error">{{ $message }}</div>
            @enderror
            @foreach ($errors->get('sample_images.*') as $messages)
                <div class="text-error">{{ $messages[0] }}</div>
            @endforeach
            @error('sample_image_order')
                <div class="text-error">{{ $message }}</div>
            @enderror
        </div>
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
