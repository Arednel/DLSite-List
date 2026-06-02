@php($isCustomCreate = $isCustomCreate ?? false)

<html lang="en" class="dark-mode">

<head>
    <title>Add</title>

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
                    <h1 class="form-page-title">{{ $isCustomCreate ? 'Add Custom Work' : 'Add Work' }}</h1>
                </div>

                <div id="content">
                    <table id="dialog" class="dialog-table" cellpadding="0" cellspacing="0">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="dialog-title dialog-header">
                                        {{ $isCustomCreate ? 'Add Custom Work' : 'Add Work' }}
                                    </div>
                                    <div class="dialog-body">
                                        <div class="create-mode-switch">
                                            <a href="{{ route('products.create', $returnParameters, false) }}"
                                                class="form-button ignore-visited-link {{ !$isCustomCreate ? 'is-active' : '' }}">
                                                DLSite Create
                                            </a>
                                            <a href="{{ route('products.create.custom', $returnParameters, false) }}"
                                                class="form-button margin-left-8 ignore-visited-link {{ $isCustomCreate ? 'is-active' : '' }}">
                                                Custom Create
                                            </a>
                                        </div>
                                        <form name="edit_work" method="post" id="main-form"
                                            action="{{ $isCustomCreate ? route('products.store.custom') : route('products.store') }}"
                                            @if ($isCustomCreate) enctype="multipart/form-data" @endif>
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                                            @foreach ($returnQuery as $queryKey => $queryValue)
                                                <input type="hidden" name="return_query[{{ $queryKey }}]"
                                                    value="{{ $queryValue }}">
                                            @endforeach
                                            <div id="top-submit-buttons"
                                                class="margin-top-8 margin-bottom-8 dialog-submit-row">
                                                <input type="submit" class="form-button submit-button" value="Submit">
                                            </div>
                                            <table class="form-fields-table" cellpadding="5" cellspacing="0"
                                                width="100%">
                                                <tbody>
                                                    @foreach ($quickAddFields as $field)
                                                        <x-fields.create-configurable-row :field="$field"
                                                            :is-custom-create="$isCustomCreate" :age-category-options="$ageCategoryOptions" :month-labels="$monthLabels"
                                                            :days="$days" :years="$years" />
                                                    @endforeach
                                                </tbody>
                                            </table>
                                            <div class="margin-top-8 margin-bottom-8 dialog-submit-row">
                                                <input type="submit" class="form-button submit-button" value="Submit">
                                            </div>
                                        </form>

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
</body>

<script src="{{ asset('scripts/dateInsert.js') }}?v={{ filemtime(public_path('scripts/dateInsert.js')) }}"></script>
<script
    src="{{ asset('scripts/autocomplete-text.js') }}?v={{ filemtime(public_path('scripts/autocomplete-text.js')) }}">
</script>

</html>
