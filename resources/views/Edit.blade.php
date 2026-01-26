<html lang="en" class="appearance-none dark-mode cvonfc">

<head>
    <title>Edit</title>

    <link rel="stylesheet" href="{{ asset('css/edit.css') }}">
</head>

<body class="page-common  ownlist_manga_update" data-ms="false" data-country-code="KZ" data-time="1741691968">
    <div id="myanimelist">
        <div class="wrapper">
            <div id="contentWrapper">
                <div>
                    <h1 class="h1">Edit Work</h1>
                </div>

                <div id="content">
                    <table id="dialog" cellpadding="0" cellspacing="0" style="width: 650px;">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="normal_header" style="margin-top: 0; text-align: left;">
                                        Edit Work
                                    </div>
                                    <div style="text-align: left;">
                                        <form name="edit_work" method="post" id="main-form"
                                            action="/update/{{ $product->id }}">
                                            @csrf

                                            <input type="hidden" name="redirect"
                                                value="{{ request('redirect', url('/')) }}">

                                            <div id="top-submit-buttons" class="mt8 mb8" style="text-align: center;">
                                                <input type="submit" class="inputButton main_submit" value="Submit">
                                            </div>
                                            <table cellpadding="5" cellspacing="0" width="100%">
                                                <tbody>
                                                    <x-fields.rj-display :id="$product->id" :work-name="$product->work_name" />
                                                    <x-fields.status-select :value="$product->progress" />
                                                    <x-fields.score-select :value="$product->score" />
                                                    <x-fields.series-field :value="$product->series" />
                                                    <x-fields.title-japanese :value="$product->work_name" required />
                                                    <x-fields.title-english :value="$product->work_name_english" />
                                                    <x-fields.genre-custom :value="implode(', ', $product->genre_custom ?? [])" />
                                                    <x-fields.notes :value="$product->notes" />
                                                    <x-fields.start-date :month-labels="$monthLabels" :days="$days" :years="$years"
                                                        :month-value="data_get($product->start_date, 'month')"
                                                        :day-value="data_get($product->start_date, 'day')"
                                                        :year-value="data_get($product->start_date, 'year')" />
                                                    <x-fields.finish-date :month-labels="$monthLabels" :days="$days" :years="$years"
                                                        :month-value="data_get($product->end_date, 'month')"
                                                        :day-value="data_get($product->end_date, 'day')"
                                                        :year-value="data_get($product->end_date, 'year')" />
                                                    <x-fields.num-re-listen-times :value="$product->num_re_listen_times" />
                                                    <x-fields.re-listen-value :value="$product->re_listen_value" />
                                                    <x-fields.priority :value="$product->priority" />
                                                </tbody>
                                            </table>
                                            <div class="mt8 mb8" style="text-align: center;">
                                                <input type="submit" class="inputButton main_submit" value="Submit">
                                            </div>
                                        </form>

                                        <form style="text-align: right;" id="delete-form" method="POST"
                                            action="/destroy/{{ $product->id }}">
                                            @csrf

                                            <input type="hidden" name="redirect"
                                                value="{{ request('redirect', url('/')) }}">

                                            <input type="submit" class="inputButton ml8 delete_submit" value="Delete"
                                                onclick="return openDeleteModal(event);">
                                        </form>

                                        <br>

                                        <div style="text-align: right;">
                                            <a href="{{ $redirect }}#{{ $product->id }}"
                                                class="inputButton ml8 ignore-visited-link">
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
                <button class="inputButton danger" onclick="confirmDeletion()">Yes, Delete</button>
                <button class="inputButton ml8" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
</body>

<script src="{{ asset('scripts/deleteConfirmation.js') }}"></script>
<script src="{{ asset('scripts/dateInsert.js') }}"></script>

</html>
