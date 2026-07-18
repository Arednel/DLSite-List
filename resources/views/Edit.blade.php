<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}" class="{{ $productFormThemeClass }}">

<head>
    <title>{{ __('Edit') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/edit.css') }}?v={{ filemtime(public_path('css/edit.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/autocomplete.css') }}?v={{ filemtime(public_path('css/autocomplete.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <x-title-tooltip-assets />
</head>

<body class="product-form-page">
    <div id="product-form-container">
        <div class="wrapper">
            <div id="contentWrapper">
                <div>
                    <h1 class="form-page-title">{{ __('Edit Work') }}</h1>
                </div>

                <div id="content">
                    <table id="dialog" class="dialog-table" cellpadding="0" cellspacing="0">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="dialog-title dialog-header">
                                        {{ __('Edit Work') }}
                                    </div>
                                    <div class="dialog-body">
                                        <form name="edit_work" method="post" id="main-form"
                                            action="{{ route('products.update', ['product' => $product]) }}">
                                            @csrf
                                            @if ($isModal)
                                                <input type="hidden" name="modal" value="1">
                                            @endif

                                            @foreach ($returnQuery as $queryKey => $queryValue)
                                                <input type="hidden" name="return_query[{{ $queryKey }}]"
                                                    value="{{ $queryValue }}">
                                            @endforeach
                                            <input type="hidden" name="return_fragment" value="{{ $returnFragment }}">

                                            <div id="top-submit-buttons"
                                                class="margin-top-8 margin-bottom-8 dialog-submit-row">
                                                <input type="submit" class="form-button submit-button"
                                                    value="{{ __('Submit') }}">
                                            </div>
                                            <table class="form-fields-table" cellpadding="5" cellspacing="0"
                                                width="100%">
                                                <tbody>
                                                    <x-fields.rj-display :id="$product->id" :work-name="$product->work_name" />
                                                    @foreach ($editFields as $field)
                                                        <x-fields.edit-configurable-row :field="$field"
                                                            :product="$product" :age-category-options="$ageCategoryOptions" :contributor-inputs="$contributorInputs"
                                                            :fetched-genres="$fetchedGenres" :custom-genres="$customGenres" :genre-fetched-input="$genreFetchedInput"
                                                            :genre-fetched-language="$genreFetchedLanguage" :genre-custom-input="$genreCustomInput" :readonly-field-values="$readonlyFieldValues"
                                                            :month-labels="$monthLabels" :days="$days" :years="$years"
                                                            :show-readonly-genre-colors="$showReadonlyGenreColors" />
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            <div class="margin-top-8 margin-bottom-8 dialog-submit-row">
                                                <input type="submit" class="form-button submit-button"
                                                    value="{{ __('Submit') }}">
                                            </div>
                                        </form>

                                        <form class="dialog-actions dialog-actions-right" id="delete-form"
                                            method="POST"
                                            action="{{ route('products.destroy', ['product' => $product]) }}">
                                            @csrf

                                            @if ($isModal)
                                                <input type="hidden" name="modal" value="1">
                                            @endif

                                            @foreach ($returnQuery as $queryKey => $queryValue)
                                                <input type="hidden" name="return_query[{{ $queryKey }}]"
                                                    value="{{ $queryValue }}">
                                            @endforeach

                                            <input type="submit" class="form-button delete-button"
                                                value="{{ __('Delete') }}" onclick="return openDeleteModal(event);">
                                        </form>

                                        <br>

                                        <div class="dialog-actions dialog-actions-right">
                                            @if ($isModal)
                                                <button type="button" class="form-button margin-left-8"
                                                    data-work-form-modal-cancel>{{ __('Close') }}</button>
                                            @else
                                                <a href="{{ $returnUrl }}"
                                                    class="form-button margin-left-8 ignore-visited-link">
                                                    {{ __('Go back') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <p>{{ __('Are you sure you want to delete this item?') }}</p>
            <div class="modal-actions">
                <button class="form-button danger" onclick="confirmDeletion()">{{ __('Yes, Delete') }}</button>
                <button class="form-button margin-left-8" onclick="closeModal()">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</body>

<script
    src="{{ asset('scripts/deleteConfirmation.js') }}?v={{ filemtime(public_path('scripts/deleteConfirmation.js')) }}">
</script>
<script src="{{ asset('scripts/dateInsert.js') }}?v={{ filemtime(public_path('scripts/dateInsert.js')) }}"></script>
<script
    src="{{ asset('scripts/autocomplete-text.js') }}?v={{ filemtime(public_path('scripts/autocomplete-text.js')) }}">
</script>
<script src="{{ asset('scripts/work-form-frame.js') }}?v={{ filemtime(public_path('scripts/work-form-frame.js')) }}">
</script>

</html>
