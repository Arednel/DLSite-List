<html lang="en" class="appearance-none dark-mode cvonfc">

<head>
    <title>Add</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/edit.css') }}?v={{ filemtime(public_path('css/edit.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>

<body class="page-common  ownlist_manga_update" data-ms="false" data-country-code="KZ" data-time="1741691968">
    <div id="myanimelist">
        <div class="wrapper">
            <div id="contentWrapper">
                <div>
                    <h1 class="h1">Add Work</h1>
                </div>

                <div id="content">
                    <table id="dialog" class="dialog-table" cellpadding="0" cellspacing="0">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="normal_header dialog-header">
                                        Add Work
                                    </div>
                                    <div class="dialog-body">
                                        <form name="edit_work" method="post" id="main-form"
                                            action="{{ route('products.store') }}">
                                            @csrf
                                            <input type="hidden" name="return_route" value="{{ $returnRoute }}">
                                            @foreach ($returnQuery as $queryKey => $queryValue)
                                                <input type="hidden" name="return_query[{{ $queryKey }}]"
                                                    value="{{ $queryValue }}">
                                            @endforeach
                                            <div id="top-submit-buttons" class="mt8 mb8 dialog-submit-row">
                                                <input type="submit" class="inputButton main_submit" value="Submit">
                                            </div>
                                            <table class="form-fields-table" cellpadding="5" cellspacing="0"
                                                width="100%">
                                                <tbody>
                                                    <x-fields.rj-input />
                                                    <x-fields.status-select />
                                                    <x-fields.score-select />
                                                    <x-fields.series-field />
                                                    <x-fields.title-japanese />
                                                    <x-fields.title-english />
                                                    <x-fields.genre-custom />
                                                    <x-fields.notes />
                                                    <x-fields.start-date :month-labels="$monthLabels" :days="$days"
                                                        :years="$years" />
                                                    <x-fields.finish-date :month-labels="$monthLabels" :days="$days"
                                                        :years="$years" />
                                                    <x-fields.num-re-listen-times />
                                                    <x-fields.re-listen-value />
                                                    <x-fields.priority />
                                                </tbody>
                                            </table>
                                            <div class="mt8 mb8 dialog-submit-row">
                                                <input type="submit" class="inputButton main_submit" value="Submit">
                                            </div>
                                        </form>

                                        <div class="dialog-actions dialog-actions-right">
                                            <a href="{{ $returnUrl }}" class="inputButton ml8 ignore-visited-link">
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

</html>
