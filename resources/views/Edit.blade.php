<html lang="en" class="dark-mode">

<head>
    <title>Edit</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/edit.css') }}?v={{ filemtime(public_path('css/edit.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/autocomplete.css') }}?v={{ filemtime(public_path('css/autocomplete.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>

<body class="product-form-page">
    <div id="product-form-container">
        <div class="wrapper">
            <div id="contentWrapper">
                <div>
                    <h1 class="form-page-title">Edit Work</h1>
                </div>

                <div id="content">
                    <table id="dialog" class="dialog-table" cellpadding="0" cellspacing="0">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="dialog-title dialog-header">
                                        Edit Work
                                    </div>
                                    <div class="dialog-body">
                                        <form name="edit_work" method="post" id="main-form"
                                            action="{{ route('products.update', ['id' => $product->id]) }}">
                                            @csrf

                                            @foreach ($returnQuery as $queryKey => $queryValue)
                                                <input type="hidden" name="return_query[{{ $queryKey }}]"
                                                    value="{{ $queryValue }}">
                                            @endforeach
                                            <input type="hidden" name="return_fragment" value="{{ $returnFragment }}">

                                            <div id="top-submit-buttons"
                                                class="margin-top-8 margin-bottom-8 dialog-submit-row">
                                                <input type="submit" class="form-button submit-button" value="Submit">
                                            </div>
                                            <table class="form-fields-table" cellpadding="5" cellspacing="0"
                                                width="100%">
                                                <tbody>
                                                    <x-fields.rj-display :id="$product->id" :work-name="$product->work_name" />
                                                    @foreach ($editFields as $field)
                                                        <x-fields.edit-configurable-row :field="$field"
                                                            :product="$product" :age-category-options="$ageCategoryOptions" :contributor-inputs="$contributorInputs"
                                                            :english-genres="$englishGenres" :custom-genres="$customGenres" :genre-fetched-english-input="$genreFetchedEnglishInput"
                                                            :genre-custom-input="$genreCustomInput" :readonly-field-values="$readonlyFieldValues" :month-labels="$monthLabels"
                                                            :days="$days" :years="$years" />
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            <div class="margin-top-8 margin-bottom-8 dialog-submit-row">
                                                <input type="submit" class="form-button submit-button" value="Submit">
                                            </div>
                                        </form>

                                        <form class="dialog-actions dialog-actions-right" id="delete-form"
                                            method="POST"
                                            action="{{ route('products.destroy', ['id' => $product->id]) }}">
                                            @csrf

                                            @foreach ($returnQuery as $queryKey => $queryValue)
                                                <input type="hidden" name="return_query[{{ $queryKey }}]"
                                                    value="{{ $queryValue }}">
                                            @endforeach

                                            <input type="submit" class="form-button delete-button" value="Delete"
                                                onclick="return openDeleteModal(event);">
                                        </form>

                                        <br>

                                        <div class="dialog-actions dialog-actions-right">
                                            <a href="{{ $returnUrl }}"
                                                class="form-button margin-left-8 ignore-visited-link">
                                                Go back
                                            </a>
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
            <p>Are you sure you want to delete this item?</p>
            <div class="modal-actions">
                <button class="form-button danger" onclick="confirmDeletion()">Yes, Delete</button>
                <button class="form-button margin-left-8" onclick="closeModal()">Cancel</button>
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

</html>
