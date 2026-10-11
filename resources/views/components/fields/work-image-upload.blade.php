<tr>
    <td width="130" class="form-table-cell image-editor-row-label">{{ __('Cover Image') }}</td>
    <td class="form-table-cell image-editor-row-content">
        <div class="image-editor-field" data-product-image-editor data-product-image-editor-kind="cover">
            @if ($editable && $product !== null)
                <input type="hidden" name="remove_work_image" value="{{ $coverRemoved ? '1' : '0' }}"
                    data-image-editor-cover-remove>
            @endif

            <div class="image-editor-cover">
                <div class="image-editor-cover__preview" data-image-editor-cover-preview
                    @if (!$coverUrl) hidden @endif>
                    <div class="image-editor-cover__media">
                        @if ($editable)
                            <button class="image-editor-remove-icon image-editor-cover__remove" type="button"
                                data-image-editor-cover-remove-button
                                aria-label="{{ __('Remove cover image') }}">×</button>
                        @endif
                        <button type="button" class="image-editor-cover-thumb index-image-viewer-trigger"
                            data-product-image-editor-viewer data-index-image-viewer-source="{{ $coverUrl ?? '' }}"
                            data-index-image-viewer-title="{{ $viewerTitle }}" aria-haspopup="dialog"
                            aria-controls="index-image-viewer-dialog" aria-label="{{ __('Preview cover image') }}">
                            <img src="{{ $coverUrl ?? '' }}" alt="" data-image-editor-cover-image>
                        </button>
                    </div>
                </div>

                @if ($editable)
                    <div class="image-editor-native-picker image-editor-native-picker--cover @if (!$coverUrl) image-editor-native-picker--empty @endif"
                        data-image-editor-cover-picker>
                        <span class="image-editor-native-picker__plus" aria-hidden="true">+</span>
                        <input id="work_image" name="work_image" class="image-editor-native-input" type="file"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.bmp" data-image-editor-cover-input>
                    </div>
                @elseif (!$coverUrl)
                    <span class="image-editor-empty">-</span>
                @endif
            </div>

            @error('work_image')
                <div class="text-error">{{ $message }}</div>
            @enderror
        </div>
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
