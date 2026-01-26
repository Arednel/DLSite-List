<html lang="en" class="appearance-none dark-mode cvonfc">

<head>
    <title>Add</title>

    <link rel="stylesheet" href="{{ asset('css/edit.css') }}">
</head>

<body class="page-common  ownlist_manga_update" data-ms="false" data-country-code="KZ" data-time="1741691968">
    <div id="myanimelist">
        <div class="wrapper">
            <div id="contentWrapper">
                <div>
                    <h1 class="h1">Add Work</h1>
                </div>

                <div id="content">
                    <table id="dialog" cellpadding="0" cellspacing="0" style="width: 650px;">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="normal_header" style="margin-top: 0; text-align: left;">
                                        Add Work
                                    </div>
                                    <div style="text-align: left;">
                                        <form name="edit_work" method="post" id="main-form" action="/store">
                                            @csrf
                                            <div id="top-submit-buttons" class="mt8 mb8" style="text-align: center;">
                                                <input type="submit" class="inputButton main_submit" value="Submit">
                                            </div>
                                            <table cellpadding="5" cellspacing="0" width="100%">
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
                                            <div class="mt8 mb8" style="text-align: center;">
                                                <input type="submit" class="inputButton main_submit" value="Submit">
                                            </div>
                                        </form>

                                        <div style="text-align: right;">
                                            <a href="{{ request('redirect', '/') }}"
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
</body>

<script src="{{ asset('scripts/dateInsert.js') }}"></script>

</html>
